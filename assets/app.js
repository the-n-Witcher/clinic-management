import './styles/app.scss';

document.addEventListener('DOMContentLoaded', function() {
    console.log('Медицинская клиника загружена');
    
    initComponents();
    initEventListeners();
    initNotifications();
    initAutoComplete();
});

function initComponents() {
    if (typeof FullCalendar !== 'undefined' && document.getElementById('calendar')) {
        initCalendar();
    }
    
    if (typeof Chart !== 'undefined' && document.querySelectorAll('.chart-container').length) {
        initCharts();
    }
    
    if (typeof $.fn.DataTable !== 'undefined') {
        initDataTables();
    }
    
    if (typeof $.fn.select2 !== 'undefined') {
        initSelect2();
    }
    
    if (typeof flatpickr !== 'undefined') {
        initDatePickers();
    }
}

function initEventListeners() {
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', handleFormSubmit);
    });
    
    document.querySelectorAll('[data-bs-toggle="modal"]').forEach(button => {
        button.addEventListener('click', handleModalOpen);
    });
    
    document.querySelectorAll('[data-validate]').forEach(input => {
        input.addEventListener('blur', validateInput);
    });
    
    document.querySelectorAll('[data-confirm]').forEach(button => {
        button.addEventListener('click', handleConfirmAction);
    });
    
    document.querySelectorAll('[data-collection]').forEach(container => {
        initCollectionFields(container);
    });
    
    const patientSearch = document.getElementById('patient-search');
    if (patientSearch) {
        patientSearch.addEventListener('input', debounce(handlePatientSearch, 300));
    }

    const doctorSearch = document.getElementById('doctor-search');
    if (doctorSearch) {
        doctorSearch.addEventListener('input', debounce(handleDoctorSearch, 300));
    }
}

function initCalendar() {
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'ru',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: '/api/appointments/calendar',
        eventClick: function(info) {
            showAppointmentDetails(info.event.id);
        },
        eventDidMount: function(info) {
            new bootstrap.Tooltip(info.el, {
                title: info.event.extendedProps.description || info.event.title,
                placement: 'top',
                trigger: 'hover',
                container: 'body'
            });
        },
        selectable: true,
        select: function(info) {
            showNewAppointmentModal(info.start, info.end);
        },
        businessHours: {
            daysOfWeek: [1, 2, 3, 4, 5], // Пн-Пт
            startTime: '09:00',
            endTime: '18:00'
        },
        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            meridiem: false
        }
    });
    calendar.render();
}

function initCharts() {
    const visitsCtx = document.getElementById('visitsChart');
    if (visitsCtx) {
        new Chart(visitsCtx, {
            type: 'line',
            data: {
                labels: ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн'],
                datasets: [{
                    label: 'Посещения',
                    data: [65, 59, 80, 81, 56, 55],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Количество посещений'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Месяцы'
                        }
                    }
                }
            }
        });
    }
    
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн'],
                datasets: [{
                    label: 'Доходы (тыс. руб.)',
                    data: [120, 190, 300, 250, 220, 350],
                    backgroundColor: [
                        'rgba(46, 204, 113, 0.8)',
                        'rgba(52, 152, 219, 0.8)',
                        'rgba(155, 89, 182, 0.8)',
                        'rgba(52, 73, 94, 0.8)',
                        'rgba(241, 196, 15, 0.8)',
                        'rgba(230, 126, 34, 0.8)'
                    ],
                    borderColor: [
                        'rgba(46, 204, 113, 1)',
                        'rgba(52, 152, 219, 1)',
                        'rgba(155, 89, 182, 1)',
                        'rgba(52, 73, 94, 1)',
                        'rgba(241, 196, 15, 1)',
                        'rgba(230, 126, 34, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Тысячи рублей'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Месяцы'
                        }
                    }
                }
            }
        });
    }
    
    const appointmentsCtx = document.getElementById('appointmentsChart');
    if (appointmentsCtx) {
        new Chart(appointmentsCtx, {
            type: 'doughnut',
            data: {
                labels: ['Завершено', 'Запланировано', 'Отменено', 'Неявка'],
                datasets: [{
                    data: [45, 25, 15, 5],
                    backgroundColor: [
                        '#2ecc71',
                        '#3498db',
                        '#e74c3c',
                        '#95a5a6'
                    ],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    title: {
                        display: true,
                        text: 'Статусы приемов'
                    }
                }
            }
        });
    }
}

function initDataTables() {
    $('.datatable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/ru.json'
        },
        pageLength: 25,
        responsive: true,
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        initComplete: function() {
            this.api().columns().every(function() {
                var column = this;
                var select = $('<select class="form-select form-select-sm"><option value="">Все</option></select>')
                    .appendTo($(column.footer()).empty())
                    .on('change', function() {
                        var val = $.fn.dataTable.util.escapeRegex($(this).val());
                        column.search(val ? '^' + val + '$' : '', true, false).draw();
                    });

                column.data().unique().sort().each(function(d, j) {
                    select.append('<option value="' + d + '">' + d + '</option>');
                });
            });
        }
    });
}

function initSelect2() {
    $('.select2').select2({
        theme: 'bootstrap-5',
        language: 'ru',
        placeholder: 'Выберите значение',
        allowClear: true,
        width: '100%'
    });
    
    $('.select2-patient').select2({
        theme: 'bootstrap-5',
        language: 'ru',
        placeholder: 'Выберите пациента',
        allowClear: true,
        width: '100%',
        ajax: {
            url: '/api/patients/search',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    q: params.term,
                    page: params.page
                };
            },
            processResults: function(data) {
                return {
                    results: data.results
                };
            },
            cache: true
        },
        minimumInputLength: 2
    });
    
    $('.select2-doctor').select2({
        theme: 'bootstrap-5',
        language: 'ru',
        placeholder: 'Выберите врача',
        allowClear: true,
        width: '100%',
        ajax: {
            url: '/api/doctors/search',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    q: params.term,
                    page: params.page
                };
            },
            processResults: function(data) {
                return {
                    results: data.results
                };
            },
            cache: true
        },
        minimumInputLength: 2
    });
}

function initDatePickers() {
    flatpickr('.datepicker', {
        dateFormat: 'd.m.Y',
        locale: 'ru',
        disableMobile: true
    });
    
    flatpickr('.datetimepicker', {
        enableTime: true,
        dateFormat: 'd.m.Y H:i',
        locale: 'ru',
        time_24hr: true,
        disableMobile: true,
        minuteIncrement: 15
    });
    
    flatpickr('.timepicker', {
        enableTime: true,
        noCalendar: true,
        dateFormat: 'H:i',
        time_24hr: true,
        locale: 'ru',
        disableMobile: true,
        minuteIncrement: 15
    });
    
    flatpickr('.daterangepicker', {
        mode: 'range',
        dateFormat: 'd.m.Y',
        locale: 'ru',
        disableMobile: true
    });
}

function initNotifications() {
    if (window.Echo) {
        window.Echo.private('App.Models.User.' + window.userId)
            .notification((notification) => {
                showNotification(notification);
            });
    }
    
    setInterval(checkNotifications, 300000); 
    
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('alert-dismissible') || 
            e.target.closest('.alert-dismissible')) {
            e.target.closest('.alert').remove();
        }
    });
}

function initAutoComplete() {
    const patientSearchInputs = document.querySelectorAll('.patient-autocomplete');
    patientSearchInputs.forEach(input => {
        new autoComplete({
            selector: '#' + input.id,
            placeHolder: 'Введите ФИО или мед. номер',
            data: {
                src: async (query) => {
                    try {
                        const response = await fetch(`/api/patients/autocomplete?q=${query}`);
                        return await response.json();
                    } catch (error) {
                        return [];
                    }
                }
            },
            resultsList: {
                element: (list, data) => {
                    if (!data.results.length) {
                        const message = document.createElement('div');
                        message.setAttribute('class', 'no_result');
                        message.innerHTML = `<span>Не найдено</span>`;
                        list.prepend(message);
                    }
                },
                noResults: true,
                maxResults: 10
            },
            resultItem: {
                highlight: true
            },
            events: {
                input: {
                    selection: (event) => {
                        const selection = event.detail.selection.value;
                        input.value = selection.name;
                        if (input.dataset.target) {
                            document.getElementById(input.dataset.target).value = selection.id;
                        }
                    }
                }
            }
        });
    });
    
    const medicationInputs = document.querySelectorAll('.medication-autocomplete');
    medicationInputs.forEach(input => {
        new autoComplete({
            selector: '#' + input.id,
            placeHolder: 'Введите название лекарства',
            data: {
                src: async (query) => {
                    try {
                        const response = await fetch(`/api/medications/autocomplete?q=${query}`);
                        return await response.json();
                    } catch (error) {
                        return [];
                    }
                }
            },
            resultsList: {
                maxResults: 15
            },
            resultItem: {
                highlight: true
            }
        });
    });
}

function initCollectionFields(container) {
    const addButton = container.querySelector('[data-collection-add]');
    const prototype = container.dataset.prototype;
    let index = container.querySelectorAll('[data-collection-item]').length;
    
    if (addButton) {
        addButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            const newItem = prototype.replace(/__name__/g, index);
            const item = document.createElement('div');
            item.classList.add('collection-item', 'mb-3', 'p-3', 'border', 'rounded');
            item.dataset.collectionItem = '';
            item.innerHTML = newItem;
            
            const removeButton = item.querySelector('[data-collection-remove]');
            if (removeButton) {
                removeButton.addEventListener('click', function() {
                    item.remove();
                });
            }
            
            container.insertBefore(item, addButton);
            index++;
            
            $(item).find('.select2').select2();
            $(item).find('.datepicker').flatpickr({
                dateFormat: 'd.m.Y',
                locale: 'ru'
            });
        });
    }
    
    container.querySelectorAll('[data-collection-remove]').forEach(button => {
        button.addEventListener('click', function() {
            this.closest('[data-collection-item]').remove();
        });
    });
}

function handleFormSubmit(e) {
    const form = e.target;
    const submitButton = form.querySelector('[type="submit"]');
    
    if (!validateForm(form)) {
        e.preventDefault();
        showAlert('Пожалуйста, исправьте ошибки в форме', 'danger');
        return;
    }
    
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Обработка...';
    }
    
    if (form.dataset.ajax === 'true') {
        e.preventDefault();
        
        const formData = new FormData(form);
        
        fetch(form.action, {
            method: form.method,
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message || 'Успешно сохранено', 'success');
                
                if (data.redirect) {
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1500);
                } else if (data.reload) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                }
            } else {
                showAlert(data.message || 'Ошибка при сохранении', 'danger');
                if (data.errors) {
                    displayFormErrors(form, data.errors);
                }
            }
        })
        .catch(error => {
            showAlert('Ошибка сети или сервера', 'danger');
            console.error('Error:', error);
        })
        .finally(() => {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = submitButton.dataset.originalText || 'Сохранить';
            }
        });
    }
}

function validateForm(form) {
    let isValid = true;
    const errors = [];
    
    form.querySelectorAll('[required]').forEach(input => {
        if (!input.value.trim()) {
            isValid = false;
            input.classList.add('is-invalid');
            errors.push(`Поле "${input.labels[0]?.textContent || input.name}" обязательно для заполнения`);
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    form.querySelectorAll('[type="email"]').forEach(input => {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (input.value && !emailRegex.test(input.value)) {
            isValid = false;
            input.classList.add('is-invalid');
            errors.push('Введите корректный email адрес');
        }
    });
    
    form.querySelectorAll('[data-phone]').forEach(input => {
        const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
        const cleanPhone = input.value.replace(/[^\d+]/g, '');
        if (input.value && !phoneRegex.test(cleanPhone)) {
            isValid = false;
            input.classList.add('is-invalid');
            errors.push('Введите корректный номер телефона');
        }
    });
    
    if (!isValid && errors.length > 0) {
        displayFormErrors(form, errors);
    }
    
    return isValid;
}

function validateInput(e) {
    const input = e.target;
    const rules = input.dataset.validate ? input.dataset.validate.split(' ') : [];
    
    input.classList.remove('is-invalid', 'is-valid');
    
    rules.forEach(rule => {
        switch(rule) {
            case 'required':
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    showInputError(input, 'Это поле обязательно');
                }
                break;
            case 'email':
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (input.value && !emailRegex.test(input.value)) {
                    input.classList.add('is-invalid');
                    showInputError(input, 'Введите корректный email');
                }
                break;
            case 'phone':
                const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
                const cleanPhone = input.value.replace(/[^\d+]/g, '');
                if (input.value && !phoneRegex.test(cleanPhone)) {
                    input.classList.add('is-invalid');
                    showInputError(input, 'Введите корректный номер телефона');
                }
                break;
            case 'numeric':
                if (input.value && isNaN(input.value)) {
                    input.classList.add('is-invalid');
                    showInputError(input, 'Введите число');
                }
                break;
        }
    });
    
    if (!input.classList.contains('is-invalid') && input.value) {
        input.classList.add('is-valid');
    }
}

function showInputError(input, message) {
    let errorElement = input.parentNode.querySelector('.invalid-feedback');
    if (!errorElement) {
        errorElement = document.createElement('div');
        errorElement.className = 'invalid-feedback';
        input.parentNode.appendChild(errorElement);
    }
    errorElement.textContent = message;
}

function displayFormErrors(form, errors) {
    form.querySelectorAll('.alert.alert-danger').forEach(alert => alert.remove());
    
    if (Array.isArray(errors)) {
        const errorList = errors.map(error => `<li>${error}</li>`).join('');
        const errorHtml = `
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <h5 class="alert-heading">Обнаружены ошибки:</h5>
                <ul class="mb-0">${errorList}</ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        form.insertAdjacentHTML('afterbegin', errorHtml);
    }
}

function handleModalOpen(e) {
    const target = e.target.dataset.bsTarget;
    const modal = document.querySelector(target);
    
    if (modal) {
        const url = e.target.dataset.url;
        if (url) {
            fetch(url)
                .then(response => response.text())
                .then(html => {
                    modal.querySelector('.modal-content').innerHTML = html;
                    initComponentsInModal(modal);
                })
                .catch(error => {
                    console.error('Error loading modal content:', error);
                });
        }
    }
}

function initComponentsInModal(modal) {
    $(modal).find('.select2').select2({
        theme: 'bootstrap-5',
        dropdownParent: modal
    });
    
    $(modal).find('.datepicker, .datetimepicker, .timepicker').flatpickr({
        disableMobile: true
    });
}

function handleConfirmAction(e) {
    const message = e.target.dataset.confirm || 'Вы уверены?';
    if (!confirm(message)) {
        e.preventDefault();
        e.stopPropagation();
    }
}

async function handlePatientSearch(e) {
    const query = e.target.value;
    if (query.length < 2) return;
    
    try {
        const response = await fetch(`/api/patients/search?q=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        const resultsContainer = document.getElementById('patient-search-results');
        if (resultsContainer) {
            resultsContainer.innerHTML = '';
            
            if (data.length > 0) {
                const list = document.createElement('ul');
                list.className = 'list-group';
                
                data.forEach(patient => {
                    const item = document.createElement('li');
                    item.className = 'list-group-item list-group-item-action';
                    item.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${patient.fullName}</strong><br>
                                <small class="text-muted">${patient.medicalNumber} • ${patient.phone}</small>
                            </div>
                            <a href="/patient/${patient.id}" class="btn btn-sm btn-outline-primary">Просмотр</a>
                        </div>
                    `;
                    list.appendChild(item);
                });
                
                resultsContainer.appendChild(list);
                resultsContainer.classList.remove('d-none');
            } else {
                resultsContainer.innerHTML = '<div class="alert alert-info">Пациенты не найдены</div>';
                resultsContainer.classList.remove('d-none');
            }
        }
    } catch (error) {
        console.error('Search error:', error);
    }
}

async function handleDoctorSearch(e) {
    const query = e.target.value;
    if (query.length < 2) return;
    
    try {
        const response = await fetch(`/api/doctors/search?q=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        const resultsContainer = document.getElementById('doctor-search-results');
        if (resultsContainer) {
            resultsContainer.innerHTML = '';
            
            if (data.length > 0) {
                const list = document.createElement('ul');
                list.className = 'list-group';
                
                data.forEach(doctor => {
                    const item = document.createElement('li');
                    item.className = 'list-group-item list-group-item-action';
                    item.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${doctor.fullName}</strong><br>
                                <small class="text-muted">${doctor.specialization} • ${doctor.phone || ''}</small>
                            </div>
                            <a href="/doctor/${doctor.id}" class="btn btn-sm btn-outline-primary">Просмотр</a>
                        </div>
                    `;
                    list.appendChild(item);
                });
                
                resultsContainer.appendChild(list);
                resultsContainer.classList.remove('d-none');
            }
        }
    } catch (error) {
        console.error('Search error:', error);
    }
}

async function showAppointmentDetails(appointmentId) {
    try {
        const response = await fetch(`/api/appointments/${appointmentId}`);
        const appointment = await response.json();
        
        const modalHtml = `
            <div class="modal fade" id="appointmentModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Детали приема #${appointment.id}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Пациент</h6>
                                    <p>
                                        <strong>${appointment.patient.fullName}</strong><br>
                                        Мед. номер: ${appointment.patient.medicalNumber}<br>
                                        Телефон: ${formatPhone(appointment.patient.phone)}
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Врач</h6>
                                    <p>
                                        <strong>${appointment.doctor.fullName}</strong><br>
                                        Специальность: ${appointment.doctor.specialization}<br>
                                        Кабинет: ${appointment.doctor.room?.number || 'Не указан'}
                                    </p>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <h6>Время приема</h6>
                                    <p>
                                        ${formatDateTime(appointment.startTime)} - ${formatDateTime(appointment.endTime)}<br>
                                        Длительность: ${appointment.duration} минут
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Статус</h6>
                                    <span class="badge ${getStatusBadgeClass(appointment.status)}">
                                        ${getStatusText(appointment.status)}
                                    </span>
                                </div>
                            </div>
                            ${appointment.reason ? `
                            <div class="mt-3">
                                <h6>Причина визита</h6>
                                <p>${appointment.reason}</p>
                            </div>
                            ` : ''}
                            ${appointment.notes ? `
                            <div class="mt-3">
                                <h6>Заметки</h6>
                                <p>${appointment.notes}</p>
                            </div>
                            ` : ''}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                            <a href="/appointments/${appointment.id}/edit" class="btn btn-primary">Редактировать</a>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        const oldModal = document.getElementById('appointmentModal');
        if (oldModal) oldModal.remove();
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        const modal = new bootstrap.Modal(document.getElementById('appointmentModal'));
        modal.show();
        
    } catch (error) {
        console.error('Error loading appointment details:', error);
        showAlert('Ошибка при загрузке деталей приема', 'danger');
    }
}

function showNewAppointmentModal(start, end) {
    const modalHtml = `
        <div class="modal fade" id="newAppointmentModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Новый прием</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="/appointments/new" method="post" data-ajax="true">
                        <div class="modal-body">
                            <input type="hidden" name="start_time" value="${start.toISOString()}">
                            <input type="hidden" name="end_time" value="${end.toISOString()}">
                            
                            <div class="mb-3">
                                <label class="form-label">Пациент *</label>
                                <select name="patient_id" class="form-select select2-patient" required>
                                    <option value="">Выберите пациента</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Врач *</label>
                                <select name="doctor_id" class="form-select select2-doctor" required>
                                    <option value="">Выберите врача</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Причина визита *</label>
                                <textarea name="reason" class="form-control" rows="3" required 
                                          placeholder="Опишите причину визита"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Заметки</label>
                                <textarea name="notes" class="form-control" rows="2" 
                                          placeholder="Дополнительные заметки"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                            <button type="submit" class="btn btn-primary">Создать прием</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    const oldModal = document.getElementById('newAppointmentModal');
    if (oldModal) oldModal.remove();
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    const modal = new bootstrap.Modal(document.getElementById('newAppointmentModal'));
    modal.show();
    
    setTimeout(() => {
        $('#newAppointmentModal .select2-patient').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#newAppointmentModal')
        });
        $('#newAppointmentModal .select2-doctor').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#newAppointmentModal')
        });
    }, 100);
}

async function checkNotifications() {
    try {
        const response = await fetch('/api/notifications/unread-count');
        const data = await response.json();
        
        if (data.count > 0) {
            updateNotificationBadge(data.count);
        }
    } catch (error) {
        console.error('Error checking notifications:', error);
    }
}

function updateNotificationBadge(count) {
    let badge = document.querySelector('.notification-badge');
    if (!badge) {
        const notificationLink = document.querySelector('a[href*="notifications"]');
        if (notificationLink) {
            badge = document.createElement('span');
            badge.className = 'notification-badge badge bg-danger rounded-pill';
            notificationLink.appendChild(badge);
        }
    }
    
    if (badge) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = count > 0 ? 'inline-block' : 'none';
    }
}

function showNotification(notification) {
    const toastHtml = `
        <div class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto">${notification.title || 'Уведомление'}</strong>
                <small class="text-muted">только что</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                ${notification.message}
                ${notification.url ? `<br><a href="${notification.url}" class="btn btn-sm btn-primary mt-2">Подробнее</a>` : ''}
            </div>
        </div>
    `;
    
    const toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        const container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(container);
    }
    
    document.querySelector('.toast-container').insertAdjacentHTML('afterbegin', toastHtml);
    
    const toast = new bootstrap.Toast(document.querySelector('.toast-container .toast'));
    toast.show();
    
    updateNotificationBadge(parseInt(document.querySelector('.notification-badge')?.textContent || 0) + 1);
}

function showAlert(message, type = 'info', duration = 5000) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    const alertContainer = document.querySelector('.alert-container') || document.body;
    alertContainer.insertAdjacentHTML('afterbegin', alertHtml);
    
    if (duration > 0) {
        setTimeout(() => {
            const alert = alertContainer.querySelector('.alert');
            if (alert) {
                bootstrap.Alert.getInstance(alert)?.close();
            }
        }, duration);
    }
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function formatPhone(phone) {
    if (!phone) return '';
    
    phone = phone.replace(/[^\d+]/g, '');
    
    if (phone.match(/^(\+7|8)(\d{3})(\d{3})(\d{2})(\d{2})$/)) {
        return phone.replace(/^(\+7|8)(\d{3})(\d{3})(\d{2})(\d{2})$/, '+7 ($2) $3-$4-$5');
    }
    
    return phone;
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function getStatusBadgeClass(status) {
    const classes = {
        'scheduled': 'bg-warning',
        'confirmed': 'bg-primary',
        'completed': 'bg-success',
        'cancelled': 'bg-danger',
        'no_show': 'bg-secondary'
    };
    return classes[status] || 'bg-secondary';
}

function getStatusText(status) {
    const texts = {
        'scheduled': 'Запланирован',
        'confirmed': 'Подтвержден',
        'completed': 'Завершен',
        'cancelled': 'Отменен',
        'no_show': 'Неявка'
    };
    return texts[status] || status;
}

window.App = {
    showAlert,
    showNotification,
    formatPhone,
    formatDateTime,
    debounce
};

window.addEventListener('error', function(e) {
    console.error('Global error:', e.error);
    showAlert('Произошла ошибка в приложении', 'danger');
});

window.addEventListener('unhandledrejection', function(e) {
    console.error('Unhandled promise rejection:', e.reason);
    showAlert('Ошибка при выполнении операции', 'danger');
});

console.log('App initialized successfully');

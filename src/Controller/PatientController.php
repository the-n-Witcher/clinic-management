<?php
// src/Controller/PatientController.php

namespace App\Controller;

use App\Entity\Patient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

class PatientController extends AbstractController
{
    #[Route('/patient/{id}', name: 'patient_show')]
    #[IsGranted('view', subject: 'patient')]
    public function show(Patient $patient)
    {
        // Автоматически проверяется через PatientVoter
        return $this->render('patient/show.html.twig', [
            'patient' => $patient
        ]);
    }
}
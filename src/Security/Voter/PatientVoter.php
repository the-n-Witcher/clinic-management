<?php

namespace App\Security\Voter;

use App\Entity\Patient;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class PatientVoter extends Voter
{
    const VIEW = 'view';
    const EDIT = 'edit';
    const DELETE = 'delete';

    private AuthorizationCheckerInterface $authorizationChecker;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    protected function supports(string $attribute, $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])) {
            return false;
        }

        if (!$subject instanceof Patient) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Patient $patient */
        $patient = $subject;

        switch ($attribute) {
            case self::VIEW:
                return $this->canView($patient, $user);
            case self::EDIT:
                return $this->canEdit($patient, $user);
            case self::DELETE:
                return $this->canDelete($patient, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function canView(Patient $patient, User $user): bool
    {
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN') || 
            $this->authorizationChecker->isGranted('ROLE_RECEPTIONIST')) {
            return true;
        }

        if ($this->authorizationChecker->isGranted('ROLE_DOCTOR') && $user->getDoctor()) {
            return $this->hasDoctorAccess($patient, $user->getDoctor());
        }

        if ($this->authorizationChecker->isGranted('ROLE_PATIENT') && $user->getPatient()) {
            return $patient->getId() === $user->getPatient()->getId();
        }

        return false;
    }

    private function canEdit(Patient $patient, User $user): bool
    {
        return $this->authorizationChecker->isGranted('ROLE_ADMIN') || 
               $this->authorizationChecker->isGranted('ROLE_RECEPTIONIST');
    }

    private function canDelete(Patient $patient, User $user): bool
    {
        return $this->authorizationChecker->isGranted('ROLE_ADMIN');
    }

    private function hasDoctorAccess(Patient $patient, $doctor): bool
    {
        foreach ($patient->getAppointments() as $appointment) {
            if ($appointment->getDoctor()->getId() === $doctor->getId()) {
                return true;
            }
        }
        return false;
    }
}
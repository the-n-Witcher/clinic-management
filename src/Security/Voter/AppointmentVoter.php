<?php

namespace App\Security\Voter;

use App\Entity\Appointment;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class AppointmentVoter extends Voter
{
    const VIEW = 'view';
    const EDIT = 'edit';
    const DELETE = 'delete';
    const CANCEL = 'cancel';

    private AuthorizationCheckerInterface $authorizationChecker;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    protected function supports(string $attribute, $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::CANCEL])) {
            return false;
        }

        if (!$subject instanceof Appointment) {
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

        /** @var Appointment $appointment */
        $appointment = $subject;

        switch ($attribute) {
            case self::VIEW:
                return $this->canView($appointment, $user);
            case self::EDIT:
                return $this->canEdit($appointment, $user);
            case self::DELETE:
                return $this->canDelete($appointment, $user);
            case self::CANCEL:
                return $this->canCancel($appointment, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function canView(Appointment $appointment, User $user): bool
    {
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN') || 
            $this->authorizationChecker->isGranted('ROLE_RECEPTIONIST')) {
            return true;
        }

        if ($this->authorizationChecker->isGranted('ROLE_DOCTOR')) {
            return $appointment->getDoctor()->getUser() === $user;
        }

        if ($this->authorizationChecker->isGranted('ROLE_PATIENT')) {
            return $appointment->getPatient()->getUser() === $user;
        }

        return false;
    }

    private function canEdit(Appointment $appointment, User $user): bool
    {
        return $this->authorizationChecker->isGranted('ROLE_ADMIN') || 
               $this->authorizationChecker->isGranted('ROLE_RECEPTIONIST');
    }

    private function canDelete(Appointment $appointment, User $user): bool
    {
        return $this->authorizationChecker->isGranted('ROLE_ADMIN');
    }

    private function canCancel(Appointment $appointment, User $user): bool
    {
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN') || 
            $this->authorizationChecker->isGranted('ROLE_RECEPTIONIST')) {
            return true;
        }

        if ($this->authorizationChecker->isGranted('ROLE_DOCTOR')) {
            return $appointment->getDoctor()->getUser() === $user;
        }

        return false;
    }
}
<?php

namespace App\Security\Voter;

use App\Entity\Doctor;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Bundle\SecurityBundle\Security;

class DoctorVoter extends Voter
{
    const VIEW = 'view';
    const EDIT = 'edit';
    const DELETE = 'delete';
    const SCHEDULE = 'schedule';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports(string $attribute, $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::SCHEDULE])) {
            return false;
        }

        if (!$subject instanceof Doctor) {
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

        /** @var Doctor $doctor */
        $doctor = $subject;

        switch ($attribute) {
            case self::VIEW:
                return $this->canView($doctor, $user);
            case self::EDIT:
                return $this->canEdit($doctor, $user);
            case self::DELETE:
                return $this->canDelete($doctor, $user);
            case self::SCHEDULE:
                return $this->canManageSchedule($doctor, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function canView(Doctor $doctor, User $user): bool
    {
        return true;
    }

    private function canEdit(Doctor $doctor, User $user): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }

    private function canDelete(Doctor $doctor, User $user): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }

    private function canManageSchedule(Doctor $doctor, User $user): bool
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if ($this->security->isGranted('ROLE_DOCTOR')) {
            return $doctor->getUser() === $user;
        }

        return false;
    }
}
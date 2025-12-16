<?php

namespace App\Security\Voter;

use App\Entity\MedicalRecord;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Bundle\SecurityBundle\Security;

class MedicalRecordVoter extends Voter
{
    const VIEW = 'view';
    const EDIT = 'edit';
    const DELETE = 'delete';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports(string $attribute, $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])) {
            return false;
        }

        if (!$subject instanceof MedicalRecord) {
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

        /** @var MedicalRecord $medicalRecord */
        $medicalRecord = $subject;

        switch ($attribute) {
            case self::VIEW:
                return $this->canView($medicalRecord, $user);
            case self::EDIT:
                return $this->canEdit($medicalRecord, $user);
            case self::DELETE:
                return $this->canDelete($medicalRecord, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function canView(MedicalRecord $medicalRecord, User $user): bool
    {
        if ($medicalRecord->isConfidential()) {
            if (!$this->security->isGranted('ROLE_DOCTOR') && 
                !$this->security->isGranted('ROLE_ADMIN')) {
                return false;
            }
        }

        if ($this->security->isGranted('ROLE_ADMIN') || 
            $this->security->isGranted('ROLE_RECEPTIONIST')) {
            return true;
        }

        if ($this->security->isGranted('ROLE_DOCTOR')) {
            return $medicalRecord->getDoctor()->getUser() === $user;
        }

        if ($this->security->isGranted('ROLE_PATIENT')) {
            return $medicalRecord->getPatient()->getUser() === $user;
        }

        return false;
    }

    private function canEdit(MedicalRecord $medicalRecord, User $user): bool
    {
        return $this->security->isGranted('ROLE_DOCTOR') || 
               $this->security->isGranted('ROLE_ADMIN');
    }

    private function canDelete(MedicalRecord $medicalRecord, User $user): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }
}
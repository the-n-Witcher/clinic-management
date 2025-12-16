<?php

namespace App\Security\Voter;

use App\Entity\Invoice;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Bundle\SecurityBundle\Security;

class InvoiceVoter extends Voter
{
    const VIEW = 'view';
    const EDIT = 'edit';
    const DELETE = 'delete';
    const PAY = 'pay';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports(string $attribute, $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::PAY])) {
            return false;
        }

        if (!$subject instanceof Invoice) {
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

        /** @var Invoice $invoice */
        $invoice = $subject;

        switch ($attribute) {
            case self::VIEW:
                return $this->canView($invoice, $user);
            case self::EDIT:
                return $this->canEdit($invoice, $user);
            case self::DELETE:
                return $this->canDelete($invoice, $user);
            case self::PAY:
                return $this->canPay($invoice, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function canView(Invoice $invoice, User $user): bool
    {
        if ($this->security->isGranted('ROLE_ADMIN') || 
            $this->security->isGranted('ROLE_RECEPTIONIST')) {
            return true;
        }

        if ($this->security->isGranted('ROLE_PATIENT')) {
            return $invoice->getPatient()->getUser() === $user;
        }

        return false;
    }

    private function canEdit(Invoice $invoice, User $user): bool
    {
        return $this->security->isGranted('ROLE_ADMIN') || 
               $this->security->isGranted('ROLE_RECEPTIONIST');
    }

    private function canDelete(Invoice $invoice, User $user): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }

    private function canPay(Invoice $invoice, User $user): bool
    {
        if ($this->security->isGranted('ROLE_PATIENT')) {
            return $invoice->getPatient()->getUser() === $user;
        }

        return $this->security->isGranted('ROLE_ADMIN') || 
               $this->security->isGranted('ROLE_RECEPTIONIST');
    }
}
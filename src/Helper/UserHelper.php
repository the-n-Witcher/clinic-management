<?php

namespace App\Helper;

use App\Entity\User;
use Symfony\Component\Security\Core\User\UserInterface;

class UserHelper
{
    public static function getUserId(?UserInterface $user): ?int
    {
        if (!$user) {
            return null;
        }
        
        if ($user instanceof User) {
            return $user->getId();
        }
        
        if (method_exists($user, 'getId')) {
            return $user->getId();
        }
        
        try {
            $reflection = new \ReflectionClass($user);
            if ($reflection->hasProperty('id')) {
                $property = $reflection->getProperty('id');
                $property->setAccessible(true);
                return $property->getValue($user);
            }
        } catch (\ReflectionException $e) {
        }
        
        return null;
    }
    
    public static function getUserIdentifier(?UserInterface $user): ?string
    {
        if (!$user) {
            return null;
        }
        
        if (method_exists($user, 'getUserIdentifier')) {
            return $user->getUserIdentifier();
        }
        
        if (method_exists($user, 'getUsername')) {
            return $user->getUsername();
        }
        
        return null;
    }
}
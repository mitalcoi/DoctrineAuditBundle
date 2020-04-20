<?php

namespace DH\DoctrineAuditBundle\User;

use Symfony\Component\Security\Core\Role\SwitchUserRole;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface as BaseUserInterface;

class TokenStorageUserProvider implements UserProviderInterface
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function getUser(): ?UserInterface
    {
        $user = null;
        $token = null;

        try {
            $token = $this->security->getToken();
        } catch (\Exception $e) {
        }

        if (null === $token) {
            return null;
        }

        $tokenUser = $token->getUser();
        if ($tokenUser instanceof BaseUserInterface) {
            $impersonation = '';
            if ($this->security->isGranted('ROLE_PREVIOUS_ADMIN')) {
                $impersonatorUser = null;
                foreach ($this->security->getToken()->getRoles() as $role) {
                    if ($role instanceof SwitchUserRole) {
                        $impersonatorUser = $role->getSource()->getUser();

                        break;
                    }
                }

                if (\is_object($impersonatorUser)) {
                    $id = method_exists($impersonatorUser, 'getId') ? $impersonatorUser->getId() : null;
                    $username = method_exists($impersonatorUser, 'getUsername') ? $impersonatorUser->getUsername() : (string) $impersonatorUser;
                    $impersonation = ' [impersonator '.$username.':'.$id.']';
                }
            }
            $id = method_exists($tokenUser, 'getId') ? $tokenUser->getId() : null;
            $user = new User($id, $tokenUser->getUsername().$impersonation);
        }

        return $user;
    }
}

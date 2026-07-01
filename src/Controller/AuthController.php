<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class AuthController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new \LogicException('Logout is handled by the security firewall.');
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        ActivityLogService $activityLogService,
    ): Response {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($userRepository->findOneByEmail((string) $user->getEmail())) {
                $this->addFlash('error', 'An account with this email already exists.');

                return $this->redirectToRoute('app_register');
            }

            $hashedPassword = $passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData());
            $user
                ->setPassword($hashedPassword)
                ->setRoles([User::ROLE_STAFF]);

            $entityManager->persist($user);
            $entityManager->flush();

            $activityLogService->logAccountCreation($user, null, $request);

            $this->addFlash('success', 'Account created successfully. You can now sign in.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/register.html.twig', [
            'form' => $form,
        ]);
    }
}

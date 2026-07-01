<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileType;
use App\Service\ActivityLogService;
use App\Service\ProfileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/profile')]
final class ProfileController extends AbstractController
{
    #[Route('', name: 'app_profile', methods: ['GET'])]
    public function show(ProfileService $profileService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(ProfileType::class, $user);

        return $this->render('profile/_modal.html.twig', [
            'user' => $user,
            'form' => $form,
            'profilePictureUrl' => $profileService->getProfilePictureUrl($user),
        ]);
    }

    #[Route('', name: 'app_profile_update', methods: ['POST'])]
    public function update(
        Request $request,
        ProfileService $profileService,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        UserPasswordHasherInterface $passwordHasher,
        ActivityLogService $activityLogService,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $oldData = $user->toProfileArray();

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return $this->json(['success' => false, 'message' => 'Invalid request. Please refresh and try again.'], Response::HTTP_BAD_REQUEST);
        }

        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $origin = $error->getOrigin();
                $fieldName = $origin?->getName() ?? '_form';
                $errors[$fieldName] = $error->getMessage();
            }

            return $this->json([
                'success' => false,
                'message' => 'Please fix the errors below.',
                'errors' => $errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $plainPassword = $form->get('plainPassword')->getData();
        if (is_string($plainPassword) && $plainPassword !== '') {
            if (strlen($plainPassword) < 8) {
                return $this->json([
                    'success' => false,
                    'message' => 'Password must be at least 8 characters.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $pictureFile = $form->get('profilePictureFile')->getData();
        $uploadedFilename = null;
        $previousFilename = null;

        if ($pictureFile) {
            try {
                $stored = $profileService->storeProfilePicture($pictureFile, $user, $slugger);
                $uploadedFilename = $stored['filename'];
                $previousFilename = $stored['oldFilename'];
                $user->setProfilePicture($uploadedFilename);
            } catch (FileException $exception) {
                return $this->json([
                    'success' => false,
                    'message' => $exception->getMessage() ?: 'Failed to upload profile picture. Please try a different image.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            } catch (\RuntimeException $exception) {
                return $this->json([
                    'success' => false,
                    'message' => 'Unable to prepare upload storage. Please contact an administrator.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            } catch (\Throwable $exception) {
                return $this->json([
                    'success' => false,
                    'message' => 'Failed to upload profile picture. Please try again.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        if (is_string($plainPassword) && $plainPassword !== '') {
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
        }

        try {
            $entityManager->persist($user);
            $entityManager->flush();
        } catch (\Throwable) {
            if ($uploadedFilename !== null) {
                $profileService->deleteProfilePictureFile($uploadedFilename);
                $user->setProfilePicture($previousFilename);
            }

            return $this->json([
                'success' => false,
                'message' => 'Failed to save profile. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($uploadedFilename !== null && $previousFilename !== null) {
            $profileService->deleteProfilePictureFile($previousFilename);
        }

        if (is_string($plainPassword) && $plainPassword !== '') {
            $activityLogService->logPasswordChange($user, $request);
        }

        $newData = $user->toProfileArray();
        unset($newData['profilePicture']);
        $sanitizedOld = $oldData;
        unset($sanitizedOld['profilePicture']);

        $activityLogService->logUpdate(
            $user,
            'Profile',
            (int) $user->getId(),
            $sanitizedOld,
            $newData,
            'Updated user profile details',
            $request,
        );

        $profilePictureUrl = $profileService->getProfilePictureUrl($user);
        $cacheBuster = $user->getUpdatedAt()?->getTimestamp() ?? time();

        return $this->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'user' => [
                'fullName' => $user->getFullName(),
                'initials' => $user->getInitials(),
                'email' => $user->getEmail(),
                'phone' => $user->getPhone(),
                'department' => $user->getDepartment(),
                'jobTitle' => $user->getJobTitle(),
                'profilePictureUrl' => $profilePictureUrl,
                'profilePictureUrlWithCache' => $profilePictureUrl ? $profilePictureUrl.'?v='.$cacheBuster : null,
                'updatedAt' => $user->getUpdatedAt()?->format('M d, Y h:i A'),
            ],
        ]);
    }
}

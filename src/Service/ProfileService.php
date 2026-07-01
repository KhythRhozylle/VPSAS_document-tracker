<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class ProfileService
{
    public const MAX_FILE_SIZE_BYTES = 5 * 1024 * 1024;

    /** @var list<string> */
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /** @var list<string> */
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    public function getProfilePictureUrl(User $user): ?string
    {
        $filename = $user->getProfilePicture();
        if ($filename === null || $filename === '') {
            return null;
        }

        $path = $this->getUploadDirectory().'/'.$filename;
        if (!is_file($path)) {
            return null;
        }

        return '/uploads/profiles/'.$filename;
    }

    public function getUploadDirectory(): string
    {
        $dir = $this->projectDir.'/public/uploads/profiles';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create profile upload directory.');
        }

        return $dir;
    }

    public function resolveFileExtension(UploadedFile $file): string
    {
        $extension = null;

        try {
            $extension = $file->guessExtension();
        } catch (\Throwable) {
            $extension = null;
        }

        $extension = strtolower($extension ?: $file->getClientOriginalExtension() ?: 'jpg');
        if ($extension === 'jpeg') {
            return 'jpg';
        }

        return $extension;
    }

    /**
     * @return array{valid: bool, message: string|null}
     */
    public function validateProfilePicture(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            return ['valid' => false, 'message' => 'Upload failed. Please try again.'];
        }

        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            return ['valid' => false, 'message' => 'Profile picture must be 5 MB or smaller.'];
        }

        $extension = $this->resolveFileExtension($file);
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return ['valid' => false, 'message' => 'Please upload a JPG, JPEG, PNG, or WEBP image.'];
        }

        try {
            $mimeType = $file->getMimeType();
        } catch (\Throwable) {
            $mimeType = null;
        }

        if ($mimeType !== null && !in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return ['valid' => false, 'message' => 'Please upload a JPG, JPEG, PNG, or WEBP image.'];
        }

        $imageInfo = @getimagesize($file->getPathname());
        if ($imageInfo === false) {
            return ['valid' => false, 'message' => 'The uploaded file is not a valid image.'];
        }

        return ['valid' => true, 'message' => null];
    }

    /**
     * @return array{filename: string, oldFilename: string|null}
     */
    public function storeProfilePicture(UploadedFile $file, User $user, SluggerInterface $slugger): array
    {
        $validation = $this->validateProfilePicture($file);
        if (!$validation['valid']) {
            throw new FileException((string) $validation['message']);
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = (string) $slugger->slug($originalFilename !== '' ? $originalFilename : 'profile');
        $extension = $this->resolveFileExtension($file);
        $newFilename = $safeFilename.'-'.uniqid('', true).'.'.$extension;
        $uploadDir = $this->getUploadDirectory();

        try {
            $file->move($uploadDir, $newFilename);
        } catch (FileException $exception) {
            throw new FileException('Unable to save profile picture. Please try again.');
        }

        return [
            'filename' => $newFilename,
            'oldFilename' => $user->getProfilePicture(),
        ];
    }

    public function deleteProfilePictureFile(?string $filename): void
    {
        if ($filename === null || $filename === '') {
            return;
        }

        $path = $this->getUploadDirectory().'/'.$filename;
        if (is_file($path)) {
            unlink($path);
        }
    }
}

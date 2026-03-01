<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Service\CaptchaService;
use App\Service\FaceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class SecurityController extends AbstractController
{
    #[Route(path: '/{_locale}/login', name: 'app_login', requirements: ['_locale' => 'fr|en|ar'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // If user is already logged in, redirect appropriately
        if ($this->getUser()) {
            $user = $this->getUser();
            if ($user instanceof User && str_ends_with(strtolower($user->getEmail()), '@wannasni.com')) {
                return $this->redirectToRoute('admin_dashboard');
            }
            return $this->redirectToRoute('app_dashboard');
        }
        
        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('front/login.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }

    #[Route(path: '/{_locale}/captcha/refresh', name: 'app_captcha_refresh', requirements: ['_locale' => 'fr|en|ar'], methods: ['GET'])]
    public function refreshCaptcha(CaptchaService $captchaService): JsonResponse
    {
        $captchaData = $captchaService->generateCaptcha();

        return $this->json([
            'success' => $captchaData !== null,
            'image'   => $captchaData ? $captchaData['image'] : null,
        ]);
    }



    #[Route(path: '/{_locale}/logout', name: 'app_logout', requirements: ['_locale' => 'fr|en|ar'])]
    public function logout(): Response
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/{_locale}/register', name: 'app_register', requirements: ['_locale' => 'fr|en|ar'], methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        FaceService $faceService
    ): Response {
        if ($request->isMethod('POST')) {
            try {
                // Get form data
                $email = $request->request->get('email');
                $password = $request->request->get('password');
                $passwordConfirm = $request->request->get('passwordConfirm');
                $firstName = $request->request->get('firstName');
                $lastName = $request->request->get('lastName');
                $phone = $request->request->get('phone');
                $role = $request->request->get('role', 'senior');
                
                // Face ID data
                $faceImageData = $request->request->get('faceImageData');
                $faceVerified = $request->request->get('faceVerified') === '1';
                $faceConsent = $request->request->has('faceConsent');
                
                // Collect all validation errors
                $errors = [];
                
                // Basic validation - check for empty fields
                if (empty($email)) {
                    $errors[] = 'Le champ email est obligatoire.';
                }
                if (empty($password)) {
                    $errors[] = 'Le champ mot de passe est obligatoire.';
                }
                if (empty($firstName)) {
                    $errors[] = 'Le champ prénom est obligatoire.';
                }
                if (empty($lastName)) {
                    $errors[] = 'Le champ nom est obligatoire.';
                }

                // Validate first name - only letters
                if (!empty($firstName) && !preg_match('/^[a-zA-ZÀ-ÿ\s\-\']+$/u', $firstName)) {
                    $errors[] = 'Le prénom ne doit contenir que des lettres.';
                }

                // Validate last name - only letters
                if (!empty($lastName) && !preg_match('/^[a-zA-ZÀ-ÿ\s\-\']+$/u', $lastName)) {
                    $errors[] = 'Le nom ne doit contenir que des lettres.';
                }

                // Validate email format
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Veuillez entrer une adresse email valide (exemple: nom@gmail.com).';
                }

                // Validate phone - only numbers and at least 8 digits
                if (!empty($phone)) {
                    $phoneDigits = preg_replace('/\D/', '', $phone); // Remove non-digits
                    if (!preg_match('/^[0-9\s\-\+\(\)]+$/', $phone)) {
                        $errors[] = 'Le numéro de téléphone ne doit contenir que des chiffres.';
                    } elseif (strlen($phoneDigits) < 8) {
                        $errors[] = 'Le numéro de téléphone doit contenir au moins 8 chiffres.';
                    }
                }

                // Validate passwords match
                if (!empty($password) && $password !== $passwordConfirm) {
                    $errors[] = 'Les mots de passe ne correspondent pas.';
                }
                
                // Validate password length
                if (!empty($password) && strlen($password) < 6) {
                    $errors[] = 'Le mot de passe doit contenir au moins 6 caractères.';
                }
                
                // Check if email uses reserved admin domain
                if (!empty($email) && str_ends_with(strtolower($email), '@wannasni.com')) {
                    $errors[] = 'Le domaine @wannasni.com est réservé aux administrateurs uniquement.';
                }
                
                // Check if email already exists
                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                    if ($existingUser) {
                        $errors[] = 'Un compte avec cet email existe déjà.';
                    }
                }

                // Validate face consent if face data is provided
                if (!empty($faceImageData) && !$faceConsent) {
                    $errors[] = 'Vous devez accepter le traitement de vos données biométriques pour utiliser la reconnaissance faciale.';
                }

                // Double-check face is not already registered
                if (!empty($faceImageData) && $faceVerified) {
                    try {
                        $match = $faceService->detectAndIdentify($faceImageData);
                        if ($match && isset($match['personId'])) {
                            // Match found - the personId is the actual user ID from the database
                            $existingFaceUser = $entityManager->getRepository(User::class)->find($match['personId']);
                            if ($existingFaceUser) {
                                $errors[] = 'Ce visage est déjà enregistré pour ' . $existingFaceUser->getFullName() . '. Veuillez vous connecter.';
                            }
                        }
                    } catch (\Exception $e) {
                        // Face verification failed, continue without blocking
                    }
                }

                // If there are any errors, display them all and return
                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        $this->addFlash('error', $error);
                    }
                    return $this->redirectToRoute('app_register', ['_locale' => $request->getLocale()]);
                }
                
                // All validations passed - create user
                $user = new User();
                $user->setEmail($email);
                $user->setFirstName($firstName);
                $user->setLastName($lastName);
                $user->setPhone($phone);
                
                // Set user domain based on selected role
                $user->setUserDomain('role.' . $role);
                
                // Map role selection to Symfony roles
                $roles = ['ROLE_USER'];
                if ($role === 'doctor' || $role === 'technicien') {
                    $roles[] = 'ROLE_CAREGIVER';
                }
                // Note: ROLE_ADMIN should be granted manually by existing admin
                $user->setRoles($roles);
                
                // Set the status explicitly to active (required field)
                $user->setStatus('active');
                
                // Hash password
                $hashedPassword = $passwordHasher->hashPassword($user, $password);
                $user->setPassword($hashedPassword);

                // First persist and flush user so we have an ID
                $entityManager->persist($user);
                $entityManager->flush();

                // Handle Face ID enrollment if face data provided
                if (!empty($faceImageData) && $faceVerified && $faceConsent) {
                    try {
                        // Save face image to disk
                        $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/faces';
                        if (!is_dir($uploadsDir)) {
                            mkdir($uploadsDir, 0755, true);
                        }
                        
                        // Generate unique filename
                        $filename = uniqid('face_') . '_' . time() . '.jpg';
                        $filepath = $uploadsDir . '/' . $filename;
                        
                        // Decode and save image
                        $imageData = $faceImageData;
                        if (str_contains($imageData, ',')) {
                            $imageData = substr($imageData, strpos($imageData, ',') + 1);
                        }
                        file_put_contents($filepath, base64_decode($imageData));
                        
                        // Set face image path on user
                        $user->setFaceImagePath('/uploads/faces/' . $filename);
                        $user->setFaceConsentAt(new \DateTime());
                        
                        // Get face encoding using Python face_recognition
                        $faceEncoding = $faceService->enrollFace(
                            $user->getFullName(),
                            (string) $user->getId(),
                            $faceImageData
                        );
                        
                        // Store face encoding on user
                        $user->setFaceEncoding($faceEncoding);
                        
                        // Update user with face data
                        $entityManager->flush();
                        
                    } catch (\Exception $e) {
                        // Face enrollment failed, log error but don't block registration
                        // The user account is already created, face can be added later
                        $this->addFlash('warning', 'Votre compte a été créé mais l\'enregistrement du visage a échoué. Vous pourrez réessayer plus tard.');
                    }
                }
                
                $this->addFlash('success', 'Votre compte a été créé avec succès ! Vous pouvez maintenant vous connecter.');
                return $this->redirectToRoute('app_login', ['_locale' => $request->getLocale()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'inscription: ' . $e->getMessage());
                return $this->redirectToRoute('app_register', ['_locale' => $request->getLocale()]);
            }
        }
        
        return $this->render('front/register.html.twig');
    }

    /**
     * AJAX endpoint for Face ID verification during registration
     * Detects face and checks for existing matches
     */
    #[Route(path: '/api/face/verify', name: 'api_face_verify', methods: ['POST'])]
    public function verifyFace(
        Request $request,
        FaceService $faceService
    ): Response {
        try {
            $data = json_decode($request->getContent(), true);
            $imageData = $data['image'] ?? null;
            
            if (!$imageData) {
                return $this->json(['success' => false, 'error' => 'No image provided'], 400);
            }
            
            // Use FaceService to verify the face
            $result = $faceService->verifyFace($imageData);
            
            return $this->json($result);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Face verification failed: ' . $e->getMessage(),
                'code' => 'ERROR'
            ], 500);
        }
    }

    /**
     * Face ID Login - authenticate user using face recognition
     */
    #[Route(path: '/api/face/login', name: 'api_face_login', methods: ['POST'])]
    public function faceLogin(
        Request $request,
        FaceService $faceService,
        EntityManagerInterface $entityManager,
        TokenStorageInterface $tokenStorage
    ): Response {
        try {
            $data = json_decode($request->getContent(), true);
            $imageData = $data['image'] ?? null;
            
            if (!$imageData) {
                return $this->json(['success' => false, 'error' => 'No image provided'], 400);
            }
            
            // Use FaceService to identify the user
            $result = $faceService->detectAndIdentify($imageData);
            
            if (!$result || !isset($result['personId'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Face not recognized or no face detected',
                    'code' => 'FACE_NOT_FOUND'
                ]);
            }
            
            // Find the user in database
            $user = $entityManager->getRepository(User::class)->find($result['personId']);
            
            if (!$user) {
                return $this->json([
                    'success' => false,
                    'error' => 'User not found',
                    'code' => 'USER_NOT_FOUND'
                ]);
            }
            
            // Check if user account is active
            if ($user->getStatus() !== 'active') {
                return $this->json([
                    'success' => false,
                    'error' => 'Votre compte a été suspendu par l\'administrateur. Veuillez contacter le support pour plus d\'informations.',
                    'code' => 'ACCOUNT_INACTIVE'
                ]);
            }
            
            // Create authentication token
            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $tokenStorage->setToken($token);
            
            // Update last login time
            $user->setLastLoginAt(new \DateTime());
            $entityManager->flush();
            
            // Determine redirect URL based on user role
            $redirectUrl = $this->generateUrl('app_dashboard', ['_locale' => $request->getLocale() ?? 'fr']);
            
            if (str_ends_with(strtolower($user->getEmail()), '@wannasni.com')) {
                $redirectUrl = $this->generateUrl('admin_dashboard');
            }
            
            return $this->json([
                'success' => true,
                'message' => 'Login successful via Face ID',
                'user' => [
                    'id' => $user->getId(),
                    'name' => $user->getFullName(),
                    'email' => $user->getEmail()
                ],
                'confidence' => $result['confidence'] ?? 0,
                'redirect' => $redirectUrl
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Face login failed: ' . $e->getMessage(),
                'code' => 'ERROR'
            ], 500);
        }
    }

    #[Route(path: '/{_locale}/forgot-password', name: 'app_forgot_password', requirements: ['_locale' => 'fr|en|ar'], methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            
            if (empty($email)) {
                $this->addFlash('error', 'Veuillez entrer votre adresse email.');
                return $this->redirectToRoute('app_forgot_password', ['_locale' => $request->getLocale()]);
            }
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Veuillez entrer une adresse email valide.');
                return $this->redirectToRoute('app_forgot_password', ['_locale' => $request->getLocale()]);
            }
            
            // Generate 6-digit verification code
            $verificationCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Generate reset token
            $resetToken = bin2hex(random_bytes(32));
            
            // Check if user exists, if not create a temporary session-based verification
            $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            
            if ($user) {
                $user->setVerificationCode($verificationCode);
                $user->setResetToken($resetToken);
                $user->setResetTokenExpiresAt(new \DateTime('+15 minutes'));
                $entityManager->flush();
            }
            
            // Store in session for any email (even non-existing users)
            $request->getSession()->set('reset_email', $email);
            $request->getSession()->set('reset_code', $verificationCode);
            $request->getSession()->set('reset_token', $resetToken);
            $request->getSession()->set('reset_expires', (new \DateTime('+15 minutes'))->getTimestamp());
            
            // Send email using Python script to ANY email entered
            try {
                $pythonPath = 'python';
                $scriptPath = $this->getParameter('kernel.project_dir') . '/send_email.py';
                
                // Execute Python script
                $command = sprintf(
                    '%s "%s" "%s" "%s"',
                    $pythonPath,
                    $scriptPath,
                    $email,
                    $verificationCode
                );
                
                $output = [];
                $returnCode = 0;
                exec($command, $output, $returnCode);
                
                if ($returnCode === 0 && !empty($output) && $output[0] === 'SUCCESS') {
                    $this->addFlash('success', 'Un code de vérification a été envoyé à ' . $email);
                    return $this->redirectToRoute('app_verify_code', ['_locale' => $request->getLocale()]);
                } else {
                    // If email fails, show the code for development/testing
                    $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email.');
                    $this->addFlash('info', 'Code de test : ' . $verificationCode);
                    return $this->redirectToRoute('app_verify_code', ['_locale' => $request->getLocale()]);
                }
            } catch (\Exception $e) {
                // If email fails, show the code for development
                $this->addFlash('error', 'Erreur : ' . $e->getMessage());
                $this->addFlash('info', 'Code de test : ' . $verificationCode);
                return $this->redirectToRoute('app_verify_code', ['_locale' => $request->getLocale()]);
            }
        }
        
        return $this->render('front/forgot_password.html.twig');
    }

    #[Route(path: '/{_locale}/verify-code', name: 'app_verify_code', requirements: ['_locale' => 'fr|en|ar'], methods: ['GET', 'POST'])]
    public function verifyCode(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $email = $request->getSession()->get('reset_email');
        
        if (!$email) {
            $this->addFlash('error', 'Session expirée. Veuillez recommencer.');
            return $this->redirectToRoute('app_forgot_password', ['_locale' => $request->getLocale()]);
        }
        
        if ($request->isMethod('POST')) {
            $code = $request->request->get('code');
            
            if (empty($code)) {
                $this->addFlash('error', 'Veuillez entrer le code de vérification.');
                return $this->redirectToRoute('app_verify_code', ['_locale' => $request->getLocale()]);
            }
            
            // Check session expiration
            $expiresAt = $request->getSession()->get('reset_expires');
            if (!$expiresAt || $expiresAt < time()) {
                $this->addFlash('error', 'Le code de vérification a expiré. Veuillez recommencer.');
                $request->getSession()->remove('reset_email');
                $request->getSession()->remove('reset_code');
                $request->getSession()->remove('reset_token');
                $request->getSession()->remove('reset_expires');
                return $this->redirectToRoute('app_forgot_password', ['_locale' => $request->getLocale()]);
            }
            
            // Verify code from session
            $sessionCode = $request->getSession()->get('reset_code');
            
            if ($sessionCode !== $code) {
                $this->addFlash('error', 'Code de vérification invalide.');
                return $this->redirectToRoute('app_verify_code', ['_locale' => $request->getLocale()]);
            }
            
            // Code is valid - check if user exists
            $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            
            if ($user) {
                // Existing user - redirect to reset password
                $request->getSession()->set('verified_email', $email);
                return $this->redirectToRoute('app_reset_password', [
                    '_locale' => $request->getLocale(),
                    'token' => $user->getResetToken()
                ]);
            } else {
                // New user - redirect to registration with email pre-filled
                $request->getSession()->set('verified_email', $email);
                $this->addFlash('info', 'Aucun compte trouvé avec cet email. Créez votre compte maintenant !');
                return $this->redirectToRoute('app_register', ['_locale' => $request->getLocale()]);
            }
        }
        
        return $this->render('front/verify_code.html.twig', [
            'email' => $email
        ]);
    }

    #[Route(path: '/{_locale}/reset-password/{token}', name: 'app_reset_password', requirements: ['_locale' => 'fr|en|ar'], methods: ['GET', 'POST'])]
    public function resetPassword(
        string $token,
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        // Check if user has verified their code
        $verifiedEmail = $request->getSession()->get('verified_email');
        
        if (!$verifiedEmail) {
            $this->addFlash('error', 'Veuillez d\'abord vérifier votre code.');
            return $this->redirectToRoute('app_forgot_password', ['_locale' => $request->getLocale()]);
        }
        
        // Find user by email
        $user = $entityManager->getRepository(User::class)->findOneBy([
            'email' => $verifiedEmail
        ]);
        
        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            $request->getSession()->remove('verified_email');
            return $this->redirectToRoute('app_forgot_password', ['_locale' => $request->getLocale()]);
        }
        
        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            $passwordConfirm = $request->request->get('passwordConfirm');
            
            $errors = [];
            
            if (empty($password)) {
                $errors[] = 'Le mot de passe est obligatoire.';
            }
            
            if (strlen($password) < 6) {
                $errors[] = 'Le mot de passe doit contenir au moins 6 caractères.';
            }
            
            if ($password !== $passwordConfirm) {
                $errors[] = 'Les mots de passe ne correspondent pas.';
            }
            
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                return $this->redirectToRoute('app_reset_password', [
                    '_locale' => $request->getLocale(),
                    'token' => $token
                ]);
            }
            
            // Update password in database
            $hashedPassword = $passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);
            $user->setVerificationCode(null);
            
            $entityManager->flush();
            
            // Clear all session data
            $request->getSession()->remove('verified_email');
            $request->getSession()->remove('reset_email');
            $request->getSession()->remove('reset_code');
            $request->getSession()->remove('reset_token');
            $request->getSession()->remove('reset_expires');
            
            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès ! Vous pouvez maintenant vous connecter.');
            return $this->redirectToRoute('app_login', ['_locale' => $request->getLocale()]);
        }
        
        return $this->render('front/reset_password.html.twig', [
            'token' => $token,
            'email' => $verifiedEmail
        ]);
    }
}

<?php

namespace App\Controller\Front;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/{_locale}/login', name: 'app_login', requirements: ['_locale' => 'fr|en|ar'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // If user is already logged in, redirect appropriately
        if ($this->getUser()) {
            $user = $this->getUser();
            if ($user instanceof User && str_ends_with(strtolower($user->getEmail()), '@wannasni.com')) {
                return $this->redirectToRoute('app_admin_choice');
            }
            return $this->redirectToRoute('app_dashboard');
        }
        
        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('front/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/{_locale}/admin-choice', name: 'app_admin_choice', requirements: ['_locale' => 'fr|en|ar'])]
    public function adminChoice(): Response
    {
        $user = $this->getUser();
        
        // Only allow @wannasni.com users
        if (!$user || !$user instanceof User || !str_ends_with(strtolower($user->getEmail()), '@wannasni.com')) {
            return $this->redirectToRoute('app_dashboard');
        }
        
        return $this->render('front/admin_choice.html.twig');
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
        EntityManagerInterface $entityManager
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
                
                // Map role selection to Symfony roles
                $roles = ['ROLE_USER'];
                if ($role === 'doctor' || $role === 'coach') {
                    $roles[] = 'ROLE_CAREGIVER';
                }
                // Note: ROLE_ADMIN should be granted manually by existing admin
                $user->setRoles($roles);
                
                // Set the status explicitly to active (required field)
                $user->setStatus('active');
                
                // Hash password
                $hashedPassword = $passwordHasher->hashPassword($user, $password);
                $user->setPassword($hashedPassword);
                
                // Persist user
                $entityManager->persist($user);
                $entityManager->flush();
                
                $this->addFlash('success', 'Votre compte a été créé avec succès ! Vous pouvez maintenant vous connecter.');
                return $this->redirectToRoute('app_login', ['_locale' => $request->getLocale()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'inscription: ' . $e->getMessage());
                return $this->redirectToRoute('app_register', ['_locale' => $request->getLocale()]);
            }
        }
        
        return $this->render('front/register.html.twig');
    }
}

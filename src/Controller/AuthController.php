<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\MagicLinkService;
use App\Service\EmailService;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Twig\Environment;

class AuthController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_my_agent');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/auth/request-magic-link', name: 'auth_request_magic_link', methods: ['POST'])]
    public function requestMagicLink(
        Request $request,
        UserRepository $userRepository,
        MagicLinkService $magicLinkService,
        EmailService $emailService,
        Environment $twig,
        LoggerInterface $logger,
    ): RedirectResponse {
        $email = trim((string) $request->request->get('email', ''));

        if (!$this->isCsrfTokenValid('request_magic_link', $request->request->get('_csrf_token'))) {
            $this->addFlash('danger', 'Invalid request. Please try again.');
            return $this->redirectToRoute('app_login');
        }

        if ($email === '' || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('danger', 'Please enter a valid email address.');
            return $this->redirectToRoute('app_login');
        }

        $user = $userRepository->findOneByEmailWithOrganisation($email);

        if ($user !== null) {
            $userOrganisations = $user->getUserOrganisations();
            $firstUo = $userOrganisations->first();

            if ($firstUo !== false && $firstUo->getOrganisation() !== null) {
                $orgUuid = $firstUo->getOrganisation()->getUuid();
                $workflowUserId = $firstUo->getWorkflowUserId() ?? (string) $user->getId();

                $appUrl = $this->getParameter('app.url');
                $magicLink = $magicLinkService->generateMagicLink(
                    $user->getName() ?? $email,
                    $orgUuid,
                    $appUrl,
                    $workflowUserId,
                    $email,
                );

                $emailHtml = $twig->render('email/magic_link.html.twig', [
                    'userName' => $user->getName() ?? 'there',
                    'magicLink' => $magicLink,
                    'app_url' => $appUrl,
                ]);

                $emailService->sendMagicLinkEmail($email, $user->getName() ?? $email, $magicLink, $emailHtml);
            }
        }

        $logger->info('Magic link requested via email form', ['email' => $email]);

        $this->addFlash('success', 'If an account with that email exists, we\'ve sent you a login link. Please check your inbox.');
        return $this->redirectToRoute('app_login');
    }

    #[Route('/auth/google', name: 'connect_google_start')]
    public function connectGoogle(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('google')
            ->redirect([
                'email',
                'profile'
            ], [
                'access_type' => 'offline',
                'prompt' => 'consent'
            ]);
    }

    #[Route('/auth/google/callback', name: 'connect_google_check')]
    public function connectGoogleCheck(Request $request, ClientRegistry $clientRegistry): Response
    {
        return new Response('');
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}

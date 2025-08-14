<?php

namespace App\Controller;

use App\Service\IpcService;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    private $ipcService;

    public function __construct(IpcService $ipcService)
    {
        $this->ipcService = $ipcService;
    }

    /**
     * @Route("/", name="app_login")
     */
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // if ($this->getUser()) {
        //     return $this->redirectToRoute('target_path');
        // }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    /**
     * @Route("/logout", name="app_logout")
     */
    public function logout()
    {
        throw new LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * @Route("/ipc/{eval}/{year}", name="app_ipc")
     */
    public function ipc(Request $request)
    {
        $ipc = $this->ipcService;
        $anio = (int) $request->get('year');
        $desde = (int) $request->get('eval');
        $val = round($ipc->calculate($anio, $desde), 7);
        return new JsonResponse([
            'Año a evaluar' => $anio,
            'Desde' => $desde,
            'Valor' => $val
        ]);
    }
}

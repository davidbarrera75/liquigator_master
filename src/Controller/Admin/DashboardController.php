<?php

namespace App\Controller\Admin;

use App\Entity\Catalogo;
use App\Entity\Information;
use App\Entity\Ipc;
use App\Entity\Parametros;
use App\Entity\SalarioMinimo;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractDashboardController
{
    /**
     * @Route("/admin", name="admin")
     */
    public function index(): Response
    {
        // redirect to some CRUD controller
        $routeBuilder = $this->get(AdminUrlGenerator::class);

        return $this->redirect($routeBuilder->setController(UserCrudController::class)->generateUrl());

        // you can also redirect to different pages depending on the current user
//        if ('jane' === $this->getUser()->getUsername()) {
//            return $this->redirect('...');
//        }

        // you can also render some template to display a proper Dashboard
        // (tip: it's easier if your template extends from @EasyAdmin/page/content.html.twig)
        // return $this->render('some/path/my-dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('LIQUIGATOR');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linktoDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToCrud('Usuarios', 'fas fa-list', User::class);
        yield MenuItem::linkToCrud('IPC', 'fas fa-list', Ipc::class);
        yield MenuItem::linkToCrud('Informes', 'fas fa-list', Information::class);
        yield MenuItem::linkToCrud('Salarios Minimos', 'fas fa-list', SalarioMinimo::class);
        yield MenuItem::subMenu('Catálogo','fas fa-cog')->setSubItems([
            MenuItem::linkToCrud('Listado', 'fas fa-angle-right', Catalogo::class),
            MenuItem::linkToCrud('Parámetros', 'fas fa-angle-right', Parametros::class),
        ]);

    }
}

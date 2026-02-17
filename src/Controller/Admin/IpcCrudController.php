<?php

namespace App\Controller\Admin;

use App\Entity\Ipc;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class IpcCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Ipc::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setDefaultSort(['anio'=>'DESC'])
            ->setPageTitle('index','IPC');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            NumberField::new('anio','Año'),
            NumberField::new('porcentaje','Porcentaje')->setNumDecimals(4),
            NumberField::new('ipc','IPC')->onlyOnIndex(),

        ];
    }

}

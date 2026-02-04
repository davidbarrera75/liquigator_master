<?php

namespace App\Controller\Admin;

use App\Entity\Parametros;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ParametrosCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Parametros::class;
    }


    public function configureFields(string $pageName): iterable
    {
        return [

            TextField::new('name'),
            TextField::new('param1'),
            TextField::new('param2'),
            AssociationField::new('catalogo')
        ];
    }
}

<?php

namespace App\Controller\Admin;

use App\Entity\Catalogo;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CatalogoCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Catalogo::class;
    }


    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('slug', 'Nombre Clave'),
            TextField::new('defaultValue', 'Valor por defecto'),
            FormField::addPanel('Lista de Parametros'),
            AssociationField::new('parametros')
        ];
    }

}

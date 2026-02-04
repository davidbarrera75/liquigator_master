<?php

namespace App\Controller\Admin;

use App\Entity\SalarioMinimo;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class SalarioMinimoCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SalarioMinimo::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setDefaultSort(['anio'=>'DESC'])
            ->setPageTitle('index','Salario Mínimo');
    }


    public function configureFields(string $pageName): iterable
    {
        return [
            IntegerField::new('anio','Año'),
            NumberField::new('valor')->setNumDecimals(2)->setTextAlign('right'),
            NumberField::new('tope')->setNumDecimals(2)->setTextAlign('right')->onlyOnIndex(),
        ];
    }

}

<?php

namespace App\Form;

use App\Entity\Configuration;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('defaultVslue', TextType::class, [
                'label' => 'Valor por Defecto',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('configValues', TextType::class, [
                'label' => 'Valores',
                'required' => false,
                'attr' => ['class' => 'form-control', 'data-role' => 'tagsinput']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Configuration::class,
        ]);
    }
}

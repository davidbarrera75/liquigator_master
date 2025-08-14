<?php


namespace App\Form;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type as FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('value', FormType\TextType::class, [
                'label' => 'Valor','attr'=>['class'=>'form-control input-sm']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
    }
}
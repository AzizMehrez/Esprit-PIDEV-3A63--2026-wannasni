<?php

namespace App\Form;

use App\Entity\Treatment;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TreatmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('senior', EntityType::class, [
                'class' => User::class,
                'choice_label' => function (User $user) {
                    return $user->getFullName() . ' (' . $user->getEmail() . ')';
                },
                // Optionally filter for seniors if logic allows
                'label' => 'Senior',
                'attr' => ['class' => 'form-control'],
                'placeholder' => 'Select a Senior'
            ])
            ->add('prescribedByDoctor', EntityType::class, [
                'class' => User::class,
                'required' => false,
                'choice_label' => function (User $user) {
                    return 'Dr. ' . $user->getFullName();
                },
                'label' => 'Prescribed By (Doctor)',
                'attr' => ['class' => 'form-control'],
                'placeholder' => 'Select a Doctor'
            ])
            ->add('medication', TextType::class, [
                'label' => 'Medication Name',
                'attr' => ['class' => 'form-control']
            ])
            ->add('dosage', TextType::class, [
                'required' => false,
                'label' => 'Dosage',
                'attr' => ['class' => 'form-control']
            ])
            ->add('frequency', TextType::class, [
                'required' => false,
                'label' => 'Frequency',
                'attr' => ['class' => 'form-control']
            ])
            ->add('instructions', TextareaType::class, [
                'required' => false,
                'label' => 'Instructions',
                'attr' => ['class' => 'form-control', 'rows' => 3]
            ])
            ->add('startDate', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Start Date',
                'attr' => ['class' => 'form-control']
            ])
            ->add('endDate', DateType::class, [
                'required' => false,
                'widget' => 'single_text',
                'label' => 'End Date',
                'attr' => ['class' => 'form-control']
            ])
            ->add('isActive', CheckboxType::class, [
                'required' => false,
                'label' => 'Is Active?',
                'attr' => ['class' => 'form-check-input']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Treatment::class,
        ]);
    }
}

<?php

namespace App\Form;

use App\Entity\HealthJournal;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HealthJournalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Senior ID is hidden/implicit in Front-End context, but if "CRUD for columns" is strict, we can add it.
            // However, usually for "My Health", the logged in user IS the senior.
            // I will add it but maybe as a read-only or selectable if an admin uses this form.
            // For now, I'll add it as a selectable field just in case, but typically this is set in controller.
            // If the user is indeed a senior managing their own, they shouldn't change this.
            // I'll skip adding 'senior' here to keep the UI clean for the end-user, 
            // relying on the Controller to set it.
            ->add('date', \Symfony\Component\Form\Extension\Core\Type\DateTimeType::class, [
                'widget' => 'single_text',
                'label' => 'Date et Heure',
                'attr' => ['class' => 'form-control']
            ])
            ->add('bloodPressureSystolic', NumberType::class, [
                'required' => false,
                'label' => 'Tension Systolique',
                'attr' => ['class' => 'form-control', 'placeholder' => '120']
            ])
            ->add('bloodPressureDiastolic', NumberType::class, [
                'required' => false,
                'label' => 'Tension Diastolique',
                'attr' => ['class' => 'form-control', 'placeholder' => '80']
            ])
            ->add('heartRate', NumberType::class, [
                'required' => false,
                'label' => 'Fréquence Cardiaque (BPM)',
                'attr' => ['class' => 'form-control', 'placeholder' => '72']
            ])
            ->add('temperature', NumberType::class, [
                'required' => false,
                'label' => 'Température (°C)',
                'attr' => ['class' => 'form-control', 'placeholder' => '37.0']
            ])
            ->add('weight', NumberType::class, [
                'required' => false,
                'label' => 'Poids (kg)',
                'attr' => ['class' => 'form-control', 'placeholder' => '70.5']
            ])
            ->add('bloodSugar', NumberType::class, [
                'required' => false,
                'label' => 'Glycémie (mg/dL)',
                'attr' => ['class' => 'form-control', 'placeholder' => '100']
            ])
            ->add('notes', TextareaType::class, [
                'required' => false,
                'label' => 'Notes Médicales',
                'attr' => ['class' => 'form-control', 'rows' => 3, 'placeholder' => 'Symptômes, médicaments pris, humeur...']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HealthJournal::class,
        ]);
    }
}

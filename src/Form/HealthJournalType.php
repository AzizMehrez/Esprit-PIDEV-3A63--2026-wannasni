<?php

namespace App\Form;

use App\Entity\HealthJournal;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HealthJournalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('senior', EntityType::class, [
                'class' => User::class,
                'choice_label' => function(User $u) { return trim(($u->getFirstName() ?? '') . ' ' . ($u->getLastName() ?? '')); },
                'placeholder' => 'Sélectionner un patient',
                'required' => false,
            ])
            ->add('date', DateTimeType::class, [
                'label' => 'Date et heure *',
                'widget' => 'single_text',
                'required' => true,
                'error_bubbling' => false,
            ])
            ->add('humeur', ChoiceType::class, [
                'choices' => [
                    'Excellente' => 'excellent',
                    'Bonne' => 'good',
                    'Moyenne' => 'average',
                    'Mauvaise' => 'poor',
                ],
                'placeholder' => 'Sélectionner votre humeur',
                'required' => false,
                'error_bubbling' => false,
            ])
            ->add('qualiteSommeil', ChoiceType::class, [
                'label' => 'Qualité du sommeil',
                'choices' => [
                    'Très bien' => 'very_good',
                    'Bien' => 'good',
                    'Moyen' => 'average',
                    'Mauvais' => 'poor',
                ],
                'placeholder' => 'Sélectionner la qualité',
                'required' => false,
                'error_bubbling' => false,
            ])
            ->add('appetit', ChoiceType::class, [
                'label' => 'Appétit',
                'choices' => [
                    'Normal' => 'normal',
                    'Augmenté' => 'increased',
                    'Diminué' => 'decreased',
                    'Absent' => 'absent',
                ],
                'placeholder' => 'Sélectionner l\'appétit',
                'required' => false,
                'error_bubbling' => false,
            ])
            ->add('niveauDouleur', IntegerType::class, [
                'label' => 'Niveau de douleur (0-10)',
                'required' => false,
                'attr' => ['min' => 0, 'max' => 10],
                'empty_data' => null,
                'error_bubbling' => false,
            ])
            ->add('symptomes', TextareaType::class, [
                'label' => 'Symptômes observés',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Décrivez les symptômes...'],
                'error_bubbling' => false,
            ])
            ->add('tensionArterielle', TextType::class, [
                'label' => 'Tension artérielle (ex: 120/80)',
                'required' => false,
                'attr' => ['placeholder' => '120/80'],
                'error_bubbling' => false,
            ])
            ->add('frequenceCardiaque', IntegerType::class, [
                'label' => 'Fréquence cardiaque (bpm)',
                'required' => false,
                'attr' => ['min' => 30, 'max' => 200],
                'empty_data' => null,
                'error_bubbling' => false,
            ])
            ->add('temperature', NumberType::class, [
                'label' => 'Température (°C)',
                'required' => false,
                'attr' => ['min' => 35.0, 'max' => 42.0, 'step' => 0.1],
                'empty_data' => null,
                'error_bubbling' => false,
            ])
            ->add('medicamentsPris', TextareaType::class, [
                'label' => 'Médicaments pris',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Listez les médicaments...'],
                'error_bubbling' => false,
            ])
            ->add('activitePhysique', TextType::class, [
                'label' => 'Activité physique du jour',
                'required' => false,
                'attr' => ['placeholder' => 'ex: Marche 30 min'],
                'error_bubbling' => false,
            ])
            ->add('hydratation', ChoiceType::class, [
                'label' => 'Hydratation',
                'choices' => [
                    'Très bien hydraté' => 'very_good',
                    'Bien hydraté' => 'good',
                    'Insuffisant' => 'insufficient',
                    'Très insuffisant' => 'very_insufficient',
                ],
                'placeholder' => 'Sélectionner l\'hydratation',
                'required' => false,
                'error_bubbling' => false,
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes additionnelles',
                'required' => false,
                'attr' => ['rows' => 4, 'placeholder' => 'Autres observations...', 'maxlength' => 1000],
                'error_bubbling' => false,
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

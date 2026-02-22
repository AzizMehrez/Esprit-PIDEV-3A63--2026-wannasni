<?php

namespace App\Form;

use App\Entity\DemandeRegime;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DemandeRegimeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Type Régime Souhaité (visible)
            ->add('typeRegimeSouhaite', ChoiceType::class, [
                'label' => 'Type de régime souhaité',
                'choices' => [
                    'Normal' => DemandeRegime::TYPE_NORMAL,
                    'Diabétique' => DemandeRegime::TYPE_DIABETIQUE,
                    'Hypo-sodé' => DemandeRegime::TYPE_HYPO_SODE,
                    'Sans gluten' => DemandeRegime::TYPE_SANS_GLUTEN,
                    'Cardioprotecteur' => DemandeRegime::TYPE_CARDIOPROTECTEUR,
                ],
                'attr' => ['class' => 'form-select'],
                'required' => true
            ])
            
            // Objectif Principal (visible)
            ->add('objectifPrincipal', ChoiceType::class, [
                'label' => 'Objectif principal',
                'choices' => [
                    'Équilibre alimentaire' => DemandeRegime::OBJECTIF_EQUILIBRE,
                    'Perte de poids' => DemandeRegime::OBJECTIF_PERTE_POIDS,
                    'Prise de masse' => DemandeRegime::OBJECTIF_PRISE_MASSE,
                    'Gestion de maladie' => DemandeRegime::OBJECTIF_GESTION_MALADIE,
                ],
                'attr' => ['class' => 'form-select'],
                'required' => true
            ])
            
            // Allergies (visible)
            ->add('allergies', TextareaType::class, [
                'label' => 'Allergies alimentaires',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Ex: Arachides, crustacés, lactose...'
                ]
            ])

            // Numéro de proche (visible)
            ->add('numeroProche', null, [
                'label' => 'Numéro du proche à prévenir',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '+33XXXXXXXXX'
                ]
            ])
            
            // Intolérances (visible)
            ->add('intolerances', TextareaType::class, [
                'label' => 'Intolérances alimentaires',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Ex: Gluten, lactose, fructose...'
                ]
            ])
            
            // Habitudes Alimentaires (visible)
            ->add('habitudesAlimentaires', TextareaType::class, [
                'label' => 'Habitudes alimentaires',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Décrivez vos habitudes alimentaires...'
                ]
            ])
            
            // Budget Mensuel (visible)
            ->add('budgetMensuel', IntegerType::class, [
                'label' => 'Budget mensuel pour l\'alimentation (en DH)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 1500'
                ]
            ])
            ->add('poids', NumberType::class, [
                'label' => 'Poids actuel (kg)',
                'required' => false,
                'scale' => 1,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: 72.5', 'step' => '0.1']
            ])
            ->add('taille', NumberType::class, [
                'label' => 'Taille (cm)',
                'required' => false,
                'scale' => 1,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: 170']
            ])
            ->add('age', IntegerType::class, [
                'label' => 'Âge',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: 68']
            ])
            ->add('niveauActivite', ChoiceType::class, [
                'label' => 'Niveau d\'activité physique',
                'required' => false,
                'choices' => [
                    'Sédentaire' => 'sedentaire',
                    'Légèrement actif' => 'leger',
                    'Modérément actif' => 'modere',
                    'Actif' => 'actif',
                    'Très actif' => 'tres_actif',
                ],
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez...'
            ]);
            
        // NE PAS AJOUTER les champs cachés ici
        // Ils seront définis directement dans le contrôleur
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DemandeRegime::class,
        ]);
    }
}

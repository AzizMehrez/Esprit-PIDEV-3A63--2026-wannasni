<?php

namespace App\Form;

use App\Entity\RegimePrescrit;
use App\Form\DataTransformer\ArrayToStringTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegimePrescritType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $arrayToStringTransformer = new ArrayToStringTransformer();
        
        $builder
            // Période du régime
            ->add('dateDebut', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'form-control',
                ]
            ])
            ->add('dateFin', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'form-control',
                ]
            ])
            
            // Type de régime
            ->add('typeRegime', ChoiceType::class, [
                'label' => 'Type de régime',
                'choices' => [
                    'Normal' => RegimePrescrit::TYPE_NORMAL,
                    'Diabétique' => RegimePrescrit::TYPE_DIABETIQUE,
                    'Hypo-sodé' => RegimePrescrit::TYPE_HYPO_SODE,
                    'Sans gluten' => RegimePrescrit::TYPE_SANS_GLUTEN,
                    'Cardioprotecteur' => RegimePrescrit::TYPE_CARDIOPROTECTEUR,
                ],
                'attr' => ['class' => 'form-select']
            ])
            
            // Informations nutritionnelles
            ->add('caloriesJournalieres', IntegerType::class, [
                'label' => 'Calories journalières',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 1800'
                ]
            ])
            
            ->add('repasParJour', ChoiceType::class, [
                'label' => 'Nombre de repas par jour',
                'choices' => [
                    '3 repas' => RegimePrescrit::REPAS_3,
                    '4 repas' => RegimePrescrit::REPAS_4,
                    '5 repas' => RegimePrescrit::REPAS_5,
                    '6 repas' => RegimePrescrit::REPAS_6,
                ],
                'attr' => ['class' => 'form-select']
            ])
            
            ->add('hydratationQuotidienne', IntegerType::class, [
                'label' => 'Hydratation quotidienne (ml)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 2000'
                ]
            ]);
            
            // Aliments avec transformation
            $alimentsRecommandes = $builder->create('alimentsRecommandes', TextareaType::class, [
                'label' => 'Aliments recommandés (séparés par des virgules)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Ex: Légumes verts, poisson grillé, fruits frais...'
                ]
            ]);
            $alimentsRecommandes->addModelTransformer($arrayToStringTransformer);
            $builder->add($alimentsRecommandes);
            
            $alimentsInterdits = $builder->create('alimentsInterdits', TextareaType::class, [
                'label' => 'Aliments interdits (séparés par des virgules)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Ex: Sucre raffiné, alcool, fritures...'
                ]
            ]);
            $alimentsInterdits->addModelTransformer($arrayToStringTransformer);
            $builder->add($alimentsInterdits);
            
            $builder
            // Recommandations
            ->add('recommandationsSpeciales', TextareaType::class, [
                'label' => 'Recommandations spéciales',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Instructions particulières, horaires...'
                ]
            ])
            
            // Suivi
            ->add('suiviRequis', ChoiceType::class, [
                'label' => 'Suivi requis',
                'choices' => [
                    'Aucun suivi' => RegimePrescrit::SUIVI_AUCUN,
                    'Suivi quotidien' => RegimePrescrit::SUIVI_QUOTIDIEN,
                    'Suivi hebdomadaire' => RegimePrescrit::SUIVI_HEBDOMADAIRE,
                ],
                'attr' => ['class' => 'form-select']
            ]);
            
            // NOTE: Les champs suivants sont gérés automatiquement par Symfony
            // car ils sont pré-remplis dans le controller :
            // - demande
            // - seniorId
            // - nutritionnisteId
            // - datePrescription
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RegimePrescrit::class,
        ]);
    }
}
<?php

namespace App\Form;

use App\Entity\BeverageProduct;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BeverageProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du produit',
                'attr' => ['placeholder' => 'Ex: Matcha Ceremonial Grade Bio'],
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => [
                    '🍵 Thé' => 'thé',
                    '☕ Café' => 'café',
                    '🌿 Infusion' => 'infusion',
                    '💧 Eau' => 'eau',
                    '🧃 Jus' => 'jus',
                    '🥤 Smoothie' => 'smoothie',
                    '💊 Complément' => 'complément',
                    '🍯 Sirop sans sucre' => 'sirop_sans_sucre',
                    '🍹 Mocktail' => 'mocktail',
                    '🌱 Superaliment' => 'superaliment',
                ],
                'placeholder' => 'Choisir une catégorie',
            ])
            ->add('shortDescription', TextType::class, [
                'label' => 'Description courte',
                'required' => false,
                'attr' => ['placeholder' => 'Résumé court du produit'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description complète',
                'required' => false,
                'attr' => ['rows' => 4, 'placeholder' => 'Description détaillée du produit...'],
            ])
            ->add('price', NumberType::class, [
                'label' => 'Prix (DH)',
                'scale' => 2,
                'attr' => ['placeholder' => '0.00', 'step' => '0.01'],
            ])
            ->add('salePrice', NumberType::class, [
                'label' => 'Prix soldé (DH)',
                'required' => false,
                'scale' => 2,
                'attr' => ['placeholder' => 'Laisser vide si pas en solde'],
            ])
            ->add('volume', TextType::class, [
                'label' => 'Volume / Quantité',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: 250g, 1L, 30 sachets'],
            ])
            ->add('brand', TextType::class, [
                'label' => 'Marque',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Pukka, Ippodo'],
            ])
            ->add('origin', TextType::class, [
                'label' => 'Origine',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Japon, Maroc, France'],
            ])
            ->add('imageUrl', TextType::class, [
                'label' => 'URL Image',
                'required' => false,
                'attr' => ['placeholder' => 'https://...'],
            ])
            ->add('caloriesPer100ml', IntegerType::class, [
                'label' => 'Calories / 100ml',
                'required' => false,
                'attr' => ['placeholder' => '0'],
            ])
            ->add('hydrationScore', IntegerType::class, [
                'label' => 'Score hydratation (0-100)',
                'required' => false,
                'attr' => ['min' => 0, 'max' => 100],
            ])
            ->add('stockQuantity', IntegerType::class, [
                'label' => 'Stock',
                'attr' => ['min' => 0],
            ])
            ->add('isSugarFree', CheckboxType::class, [
                'label' => 'Sans sucre',
                'required' => false,
            ])
            ->add('isCaffeineFree', CheckboxType::class, [
                'label' => 'Sans caféine',
                'required' => false,
            ])
            ->add('isBio', CheckboxType::class, [
                'label' => 'Bio',
                'required' => false,
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Actif (visible en boutique)',
                'required' => false,
            ])
            ->add('isFeatured', CheckboxType::class, [
                'label' => 'Produit vedette',
                'required' => false,
            ])
            ->add('compatibleRegimes', ChoiceType::class, [
                'label' => 'Régimes compatibles',
                'choices' => [
                    'Normal' => 'normal',
                    'Diabétique' => 'diabétique',
                    'Cardioprotecteur' => 'cardioprotecteur',
                    'Sans gluten' => 'sans_gluten',
                    'Hypo-sodé' => 'hypo_sodé',
                ],
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BeverageProduct::class,
        ]);
    }
}

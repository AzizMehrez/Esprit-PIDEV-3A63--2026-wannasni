<?php

namespace App\Form;

use App\Entity\Treatment;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TreatmentType extends AbstractType
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
            ->add('docteur', EntityType::class, [
                'class' => User::class,
                'choice_label' => function(User $u) { return trim(($u->getFirstName() ?? '') . ' ' . ($u->getLastName() ?? '')); },
                'required' => false,
                'placeholder' => 'Sélectionner un docteur (facultatif)'
            ])
            ->add('datePrescription')
            ->add('medicaments')
            ->add('posologie')
            ->add('frequence')
            ->add('dateDebut')
            ->add('dateFin')
            ->add('instructions')
            ->add('renouvellements')
            ->add('statut')
            ->add('effetsSecondaires')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Treatment::class,
        ]);
    }
}

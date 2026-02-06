<?php

namespace App\Form;

use App\Entity\HealthJournal;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
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
            ->add('date')
            ->add('humeur')
            ->add('qualiteSommeil')
            ->add('appetit')
            ->add('niveauDouleur')
            ->add('symptomes')
            ->add('tensionArterielle')
            ->add('frequenceCardiaque')
            ->add('temperature')
            ->add('medicamentsPris')
            ->add('activitePhysique')
            ->add('hydratation')
            ->add('notes')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HealthJournal::class,
        ]);
    }
}

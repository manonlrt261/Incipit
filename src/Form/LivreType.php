<?php

namespace App\Form;

use App\Entity\Genre;
use App\Entity\Livre;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LivreType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre du livre',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Saisir le titre du livre',
                    'maxlength' => 255,
                ],
            ])

            ->add('sousTitre', TextType::class, [
                'label' => 'Sous-titre',
                'required' => false,
            ])

            ->add('isbn13', TextType::class, [
                'label' => 'ISBN-13',
                'required' => false,
                'attr' => [
                    'maxlength' => 13,
                ],
            ])

            ->add('isbn10', TextType::class, [
                'label' => 'ISBN-10',
                'required' => false,
                'attr' => [
                    'maxlength' => 10,
                ],
            ])

            ->add('saga', TextType::class, [
                'label' => 'Saga',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Nom de la saga',
                ],
            ])

            ->add('tome', IntegerType::class, [
                'label' => 'Tome',
                'required' => false,
                'attr' => [
                    'min' => 1,
                ],
            ])

            ->add('genres', EntityType::class, [
                'class' => Genre::class,
                'choice_label' => 'nom',
                'label' => 'Genre(s)',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
            ])

            ->add('edition', TextType::class, [
                'label' => 'Édition',
                'required' => false,
            ])

            ->add('langue', TextType::class, [
                'label' => 'Langue',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Français, anglais...',
                ],
            ])

            ->add('datePublication', DateType::class, [
                'label' => 'Date de publication',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime',
            ])

            ->add('nombrePages', IntegerType::class, [
                'label' => 'Nombre de pages',
                'required' => false,
                'attr' => [
                    'min' => 1,
                ],
            ])

            ->add('resume', TextareaType::class, [
                'label' => 'Résumé',
                'required' => false,
                'attr' => [
                    'rows' => 5,
                ],
            ])

            ->add('imageCouverture', TextType::class, [
                'label' => 'URL de la couverture',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' => Livre::class,
        ]);
    }
}

<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;

class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'options' => [
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                ],
                'first_options' => [
                    'constraints' => [
                        new NotBlank(
                            message: 'Veuillez saisir un mot de passe.',
                        ),
                        new Length(
                            min: 12,
                            minMessage: 'Votre mot de passe doit contenir au moins {{ limit }} caractères.',
                            // Limite la longueur traitée par le hachage du mot de passe.
                            max: 4096,
                        ),
                        new PasswordStrength(message: 'Ce mot de passe est trop faible. Choisissez un mot de passe plus robuste.'),
                        new NotCompromisedPassword(message: 'Ce mot de passe figure dans une fuite de données. Choisissez-en un autre.'),
                    ],
                    'label' => 'Nouveau mot de passe',
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                ],
                'invalid_message' => 'Les deux mots de passe doivent être identiques.',
                // Le contrôleur hache la saisie avant de l’enregistrer sur le compte.
                'mapped' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}

<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class AppFixtures extends Fixture
{
    const USER_ADMIN = 'deiberdev@gmail.com';
    private $encoder;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->encoder = $passwordEncoder;
    }

    public function load(ObjectManager $manager)
    {
        // $product = new Product();
        // $manager->persist($product);
        $this->createUsers($manager);
        $manager->flush();
    }

    private function createUsers(ObjectManager $manager)
    {
        $user = $manager->getRepository(User::class)->findOneBy(['email' => self::USER_ADMIN]);
        if (!$user) {
            $user = new User();
            $user->setRoles(['ROLE_ADMIN']);
            $user->setEmail(self::USER_ADMIN);
            $user->setFullName('ADMINISTRADOR');
            $user->setPassword($this->encoder->encodePassword($user, 123456));
            $manager->persist($user);
        }

    }
}

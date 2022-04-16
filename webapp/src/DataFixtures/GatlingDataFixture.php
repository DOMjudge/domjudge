<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\DataFixtures\DefaultData\AbstractDefaultDataFixture;
use App\DataFixtures\ExampleData\ProblemFixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Language;
use App\Entity\TeamCategory;
use App\Entity\Role;
use App\Entity\User;

/**
 * Class GatlingDataFixture
 * @package App\DataFixtures
 */
class GatlingDataFixture extends AbstractDefaultDataFixture implements FixtureGroupInterface, DependentFixtureInterface
{
    /**
     * @inheritDoc
     */
    public static function getGroups(): array
    {
        return ['gatling'];
    }

    public function load(ObjectManager $manager): void
    {
        $num_jury_accounts = 25;

        // Enable a bunch of languages:
        $langs = ["csharp", "adb", "f95", "hs", "lua", "pas", "py3", "rb", "scala", "kt"];
        foreach($langs as $langid) {
            $lang = $manager->getRepository(Language::class)->findOneBy(['langid' => $langid]);
            $lang->setAllowSubmit(true);
            $manager->persist($lang);
        }


        // Create a contest
        $gatlingContest = new Contest();
        $gatlingContest
            ->setExternalid('gatling')
            ->setName('gatling contest')
            ->setShortname('gatling')
            ->setStarttimeString(
                sprintf(
                    '%s-01-01 12:00:00 Europe/Amsterdam',
                    date('Y')
                )
            )
            ->setActivatetimeString('-00:30:00.123')
            ->setFreezetimeString(
                sprintf(
                    '%s-01-01 16:00:00 Europe/Amsterdam',
                    date('Y') + 2
                )
            )
            ->setEndtimeString(
                sprintf(
                    '%s-01-01 17:00:00 Europe/Amsterdam',
                    date('Y') + 2
                )
            )
            ->setUnfreezetimeString(
                sprintf(
                    '%s-01-01 17:30:00 Europe/Amsterdam',
                    date('Y') + 2
                )
            )
            ->setDeactivatetimeString(
                sprintf(
                    '%s-01-01 18:30:00 Europe/Amsterdam',
                    date('Y') + 2
                )
            );
        $manager->persist($gatlingContest);

        // Add problem to the contest.
        $helloDemo = new ContestProblem();
        $helloDemo
            ->setShortname('hello')
            ->setContest($gatlingContest)
            ->setProblem($this->getReference(ProblemFixture::HELLO_REFERENCE))
            ->setColor('magenta');

        $manager->persist($helloDemo);

        // Create a category to associate the gatling stuff with.
        $category = new TeamCategory();
        $category
            ->setName('gatling')
            ->setSortorder(1)
            ->setColor('#ff9e2a') // This is gatling orange.
            ->setAllowSelfRegistration(true)
            ->setVisible(true);

        $manager->persist($category);

        // Create some jury users for gatling to use.
        for($i=0; $i<$num_jury_accounts; $i++) {
            $acct_name = sprintf('gatling_jury_%05d', $i);
            $user = new User();
            $user
                ->setUsername($acct_name)
                ->setName("gatling jury $i")
                ->setPlainPassword($acct_name)
                ->addUserRole($manager->getRepository(Role::class)->findOneBy(['dj_role' => 'jury']));
            $manager->persist($user);
        }
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [ProblemFixture::class];
    }
}

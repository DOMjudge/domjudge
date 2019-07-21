<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Role;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BaylorCmsService
{
    const BASE_URI = 'https://icpc.baylor.edu';
    const WS_TOKEN_URL = '/auth/realms/cm5/protocol/openid-connect/token';
    const WS_CLICS = '/cm5-contest-rest/rest/contest/export/CLICS/CONTEST/';

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var HttpClientInterface
     */
    protected $client;

    /**
     * BaylorCmsService constructor.
     * @param DOMJudgeService        $dj
     * @param EntityManagerInterface $em
     * @param                        $domjudgeVersion
     */
    public function __construct(
        DOMJudgeService $dj,
        EntityManagerInterface $em,
        $domjudgeVersion
    ) {
        $this->dj     = $dj;
        $this->em     = $em;
        $this->client = HttpClient::create(
            [
                'base_uri' => self::BASE_URI,
                'headers' => [
                    'User-Agent' => 'DOMjudge/' . $domjudgeVersion,
                    'Accept' => 'application/json',
                ]
            ]
        );
    }

    /**
     * Import teams from the Baylor CMS
     * @param string      $token
     * @param string      $contest
     * @param string|null $message
     * @return bool
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws DecodingExceptionInterface
     */
    public function importTeams(string $token, string $contest, string &$message = null): bool
    {
        $bearerToken = $this->getBearerToken($token, $message);
        if ($bearerToken === null) {
            return false;
        }
        $response = $this->client->request('GET', self::WS_CLICS . $contest, [
            'headers' => [
                'Authorization' => 'bearer ' . $bearerToken
            ],
        ]);

        if ($response->getStatusCode() >= 400 && $response->getStatusCode() < 500) {
            $message = sprintf('Access forbidden, is your token valid? Did you specify the correct contest ID? %s',
                               $response->getContent(false));
            return false;
        }
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            $message = sprintf('Unknown error while retrieving data from icpc.baylor.edu, status code: %d, %s',
                               $response->getStatusCode(), $response->getContent(false));
            return false;
        }

        $json = $response->toArray();

        if ($json === null) {
            $message = sprintf('Error retrieving API data. API gave us: %s', $response->getContent());
            return false;
        }

        $participants = $this->em->getRepository(TeamCategory::class)->findOneBy(['name' => 'Participants']);
        $teamRole     = $this->em->getRepository(Role::class)->findOneBy(['dj_role' => 'team']);

        foreach ($json['contest']['group'] as $group) {
            $siteName = $group['groupName'];
            foreach ($group['team'] as $teamData) {
                $institutionName = $teamData['institutionName'];
                // Note: affiliations are not updated and not deleted even if all teams have canceled
                $affiliation = $this->em->getRepository(TeamAffiliation::class)->findOneBy(['name' => $institutionName]);
                if ($affiliation === null) {
                    $shortName   = isset($teamData['institutionShortName']) ? $teamData['institutionShortName'] : $institutionName;
                    $affiliation = new TeamAffiliation();
                    $affiliation
                        ->setName($institutionName)
                        ->setShortname($shortName)
                        ->setCountry($teamData['country']);
                    $this->em->persist($affiliation);
                    $this->em->flush();
                }

                /*
                 * FIXME: team members are behind a different API call and not important for now
                 */

                $team = $this->em->getRepository(Team::class)->findOneBy(['externalid' => $teamData['teamId']]);
                // Note: teams are not deleted but disabled depending on their status
                $enabled = $teamData['status'] === 'ACCEPTED';
                if ($team === null) {
                    $team = new Team();
                    $team
                        ->setName($teamData['teamName'])
                        ->setCategory($participants)
                        ->setAffiliation($affiliation)
                        ->setEnabled($enabled)
                        ->setComments('Status: ' . $teamData['status'])
                        ->setExternalid($teamData['teamId'])
                        ->setRoom($siteName);
                    $this->em->persist($team);
                    $this->em->flush();
                    $username = sprintf("team%04d", $team->getTeamid());
                    $user     = new User();
                    $user
                        ->setUsername($username)
                        ->setName($teamData['teamName'])
                        ->setTeam($team)
                        ->addUserRole($teamRole);

                    $this->em->persist($user);
                    $this->em->flush();
                } else {
                    $username = sprintf("team%04d", $team->getTeamid());
                    $team
                        ->setName($teamData['teamName'])
                        ->setCategory($participants)
                        ->setAffiliation($affiliation)
                        ->setEnabled($enabled)
                        ->setComments('Status: ' . $teamData['status'])
                        ->setExternalid($teamData['teamId'])
                        ->setRoom($siteName);

                    $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
                    if ($user !== null) {
                        $user->setName($teamData['teamName']);
                    }

                    $this->em->flush();
                }
            }
        }

        return true;
    }

    /**
     * Upload standings to the Baylor CMS
     * @param string      $token
     * @param string      $contest
     * @param string|null $message
     * @return bool
     */
    public function uploadStandings(string $token, string $contest, string &$message = null)
    {
        // TODO: reimplement

        $message = 'Sorry, standings upload is broken because of a format change.';
        return false;
    }

    /**
     * Convert the given web service token to a bearer token
     * @param string      $token
     * @param string|null $message
     * @return string|null
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws DecodingExceptionInterface
     */
    protected function getBearerToken(string $token, string &$message = null)
    {
        $response = $this->client->request('POST', self::WS_TOKEN_URL, [
            'body' => [
                'client_id' => 'cm5-token',
                'username' => 'token:' . $token,
                'password' => '',
                'grant_type' => 'password'
            ]
        ]);

        if ($response->getStatusCode() >= 400 && $response->getStatusCode() < 500) {
            $message = sprintf('Access forbidden, is your token valid? %s',
                               $response->getContent(false));
            return null;
        }
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            $message = sprintf('Unknown error while retrieving data from icpc.baylor.edu, status code: %d, %s',
                               $response->getStatusCode(), $response->getContent(false));
            return null;
        }

        $body = $response->toArray();
        return $body['access_token'];
    }
}

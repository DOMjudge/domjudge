<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use Generator;

class GroupControllerTest extends BaseTestCase
{
    protected ?string $apiEndpoint = 'groups';

    protected array $expectedObjects = [
        '2' => [
            'hidden'    => false,
            'icpc_id'   => null,
            'id'        => '2',
            'name'      => 'Self-Registered',
            'sortorder' => 8,
            'color'     => '#33cc44'
        ],
        '3' => [
            'hidden'    => false,
            'icpc_id'   => null,
            'id'        => '3',
            'name'      => 'Participants',
            'sortorder' => 0,
            'color'     => null
        ],
        '4' => [
            'hidden'    => false,
            'icpc_id'   => null,
            'id'        => '4',
            'name'      => 'Observers',
            'sortorder' => 1,
            'color'     => '#ffcc33'
        ]
    ];

    protected array $newGroupsPostData = [
        ['name' => 'newGroup',
         'icpc_id' => 'icpc100',
         'hidden' => false,
         'sortorder' => 1,
         'color' => '#0077B3'],
        ['name' => 'newSelfRegister',
         'hidden' => false,
         'sortorder' => 2,
         'color' => 'red',
         'allow_self_registration' => true],
    ];
    protected array $restrictedProperties = ['hidden', 'allow_self_registration'];

    // We test explicitly for groups 1 and 5 here, which are hidden groups and
    // should not be returned for non-admin users.
    protected array $expectedAbsent = ['4242', 'nonexistent', '1', '5'];

    /**
     * @dataProvider provideNewAddedGroup
     */
    public function testNewAddedGroup(array $newGroupPostData): void
    {
        $url = $this->helperGetEndpointURL($this->apiEndpoint);
        $objectsBeforeTest = $this->verifyApiJsonResponse('GET', $url, 200, $this->apiUser);

        $returnedObject = $this->verifyApiJsonResponse('POST', $url, 201, 'admin', $newGroupPostData);
        foreach ($newGroupPostData as $key => $value) {
            self::assertEquals($value, $returnedObject[$key]);
        }

        $objectsAfterTest  = $this->verifyApiJsonResponse('GET', $url, 200, $this->apiUser);
        $newItems = array_map('unserialize', array_diff(array_map('serialize', $objectsAfterTest), array_map('serialize', $objectsBeforeTest)));
        self::assertEquals(1, count($newItems));
        $listKey = array_keys($newItems)[0];
        foreach ($newGroupPostData as $key => $value) {
            self::assertEquals($value, $newItems[$listKey][$key]);
        }
    }

    public function testNewAddedGroupPostWithId(): void
    {
        $url = $this->helperGetEndpointURL($this->apiEndpoint);
        $postWithId = $this->newGroupsPostData[0];
        $postWithId['id'] = '1';
        // CLICS does not allow POST to set the id value
        $this->verifyApiJsonResponse('POST', $url, 400, 'admin', $postWithId);
    }

    public function provideNewAddedGroup(): Generator
    {
        foreach ($this->newGroupsPostData as $group) {
            yield [$group];
        }
    }
}

<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use PHPUnit\Framework\Attributes\DataProvider;
use Generator;

class GroupControllerTest extends BaseTestCase
{
    protected ?string $apiEndpoint = 'groups';

    protected array $expectedObjects = [
        'self-registered' => [
            'hidden'    => false,
            'icpc_id'   => null,
            'id'        => 'self-registered',
            'name'      => 'Self-Registered',
            'sortorder' => 8,
            'color'     => '#33cc44'
        ],
        'participants' => [
            'hidden'    => false,
            'icpc_id'   => null,
            'id'        => 'participants',
            'name'      => 'Participants',
            'sortorder' => 0,
            'color'     => null
        ],
        'observers' => [
            'hidden'    => false,
            'icpc_id'   => null,
            'id'        => 'observers',
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

    #[DataProvider('provideNewAddedGroup')]
    public function testNewAddedGroup(array $newGroupPostData): void
    {
        $url = $this->helperGetEndpointURL($this->apiEndpoint);
        $objectsBeforeTest = $this->verifyApiJsonResponse('GET', $url, 200, $this->apiUser);

        $returnedObject = $this->verifyApiJsonResponse('POST', $url, 201, 'admin', $newGroupPostData);
        foreach ($newGroupPostData as $key => $value) {
            self::assertEquals($value, $returnedObject[$key]);
        }

        $objectsAfterTest  = $this->verifyApiJsonResponse('GET', $url, 200, $this->apiUser);
        $newItems = array_map(unserialize(...), array_diff(array_map(serialize(...), $objectsAfterTest), array_map(serialize(...), $objectsBeforeTest)));
        self::assertCount(1, $newItems);
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
        // CLICS does not allow POST to set the id value. Our API will just ignore the property
        $returnedObject = $this->verifyApiJsonResponse('POST', $url, 201, 'admin', $postWithId);
        self::assertNotEquals($returnedObject['id'], $postWithId['id']);
    }

    #[DataProvider('provideNewAddedGroup')]
    public function testNewAddedGroupPut(array $newGroupPostData): void
    {
        $url = $this->helperGetEndpointURL($this->apiEndpoint);
        $objectsBeforeTest = $this->verifyApiJsonResponse('GET', $url, 200, $this->apiUser);

        $newGroupPostData['id'] = 'someid';

        $returnedObject = $this->verifyApiJsonResponse('PUT', $url . '/someid', 201, 'admin', $newGroupPostData);
        foreach ($newGroupPostData as $key => $value) {
            self::assertEquals($value, $returnedObject[$key]);
        }

        $objectsAfterTest  = $this->verifyApiJsonResponse('GET', $url, 200, $this->apiUser);
        $newItems = array_map(unserialize(...), array_diff(array_map(serialize(...), $objectsAfterTest), array_map(serialize(...), $objectsBeforeTest)));
        self::assertCount(1, $newItems);
        $listKey = array_keys($newItems)[0];
        foreach ($newGroupPostData as $key => $value) {
            self::assertEquals($value, $newItems[$listKey][$key]);
        }
    }

    #[DataProvider('provideNewAddedGroup')]
    public function testNewAddedGroupPutWithoutId(array $newGroupPostData): void
    {
        $url = $this->helperGetEndpointURL($this->apiEndpoint);
        $returnedObject = $this->verifyApiJsonResponse('PUT', $url . '/someid', 400, 'admin', $newGroupPostData);
        self::assertStringContainsString('ID in URL does not match ID in payload', $returnedObject['message']);
    }

    #[DataProvider('provideNewAddedGroup')]
    public function testNewAddedGroupPutWithDifferentId(array $newGroupPostData): void
    {
        $newGroupPostData['id'] = 'someotherid';
        $url = $this->helperGetEndpointURL($this->apiEndpoint);
        $returnedObject = $this->verifyApiJsonResponse('PUT', $url . '/someid', 400, 'admin', $newGroupPostData);
        self::assertStringContainsString('ID in URL does not match ID in payload', $returnedObject['message']);
    }

    public function provideNewAddedGroup(): Generator
    {
        foreach ($this->newGroupsPostData as $group) {
            yield [$group];
        }
    }

    public function testNewAddedGroupWithInvalidId(): void
    {
        $url = $this->helperGetEndpointURL($this->apiEndpoint);
        $data = ['id' => 'invalid id!', 'name' => 'newGroup', 'hidden' => false, 'sortorder' => 1, 'color' => '#0077B3'];
        $response = $this->verifyApiJsonResponse('PUT', $url . '/invalid%20id!', 400, 'admin', $data);
        self::assertStringContainsString('Only letters, numbers, dashes, underscores and dots are allowed.', $response['message']);
    }

    public function testPutReplacesGroup(): void
    {
        $url = $this->helperGetEndpointURL($this->apiEndpoint, 'observers');
        $data = ['id' => 'observers', 'name' => 'Replaced'];
        $response = $this->verifyApiJsonResponse('PUT', $url, 200, 'admin', $data);
        self::assertEquals('Replaced', $response['name']);
        self::assertEquals(0, $response['sortorder']);
        self::assertArrayNotHasKey('color', $response);
    }

    public function testPutWithTypesAndCssClass(): void
    {
        $url = $this->helperGetEndpointURL($this->apiEndpoint, 'someid');
        $data = ['id' => 'someid', 'name' => 'Styled', 'types' => ['css-class'], 'css_class' => 'styled-row'];
        $response = $this->verifyApiJsonResponse('PUT', $url, 201, 'admin', $data);
        self::assertEquals(['css-class'], $response['types']);
        self::assertEquals('styled-row', $response['css_class']);
    }

    public function testPutWithInvalidType(): void
    {
        $url = $this->helperGetEndpointURL($this->apiEndpoint, 'someid');
        $data = ['id' => 'someid', 'name' => 'Styled', 'types' => ['nonexistent']];
        $response = $this->verifyApiJsonResponse('PUT', $url, 400, 'admin', $data);
        self::assertStringContainsString('types', $response['message']);
    }

    public function testPatchKeepsOtherFields(): void
    {
        $url = $this->helperGetEndpointURL($this->apiEndpoint, 'observers');
        $before = $this->verifyApiJsonResponse('GET', $url, 200, 'admin');

        $response = $this->verifyApiJsonResponse('PATCH', $url, 200, 'admin', ['name' => 'Patched', 'hidden' => true]);
        self::assertEquals('Patched', $response['name']);
        self::assertTrue($response['hidden']);
        self::assertEquals('#ffcc33', $response['color']);
        self::assertEquals(1, $response['sortorder']);
        self::assertEquals($before['types'], $response['types']);
    }

    public function testPatchClearsNullFields(): void
    {
        $url = $this->helperGetEndpointURL($this->apiEndpoint, 'participants');
        $this->verifyApiJsonResponse('PATCH', $url, 200, 'admin', ['icpc_id' => 'icpc1']);

        $response = $this->verifyApiJsonResponse('PATCH', $url, 200, 'admin', ['icpc_id' => null]);
        self::assertNull($response['icpc_id']);
        self::assertEquals('Participants', $response['name']);
    }

    public function testAllowPasswordChange(): void
    {
        $url = $this->helperGetEndpointURL($this->apiEndpoint, 'participants');
        $response = $this->verifyApiJsonResponse('PATCH', $url, 200, 'admin', ['allow_password_change' => true]);
        self::assertTrue($response['allow_password_change']);
        self::assertEquals('Participants', $response['name']);

        $response = $this->verifyApiJsonResponse('PUT', $url, 200, 'admin', ['id' => 'participants', 'name' => 'Participants']);
        self::assertFalse($response['allow_password_change']);
    }

    public function testPatchNotFound(): void
    {
        $url = $this->helperGetEndpointURL($this->apiEndpoint, 'nonexistent');
        $this->verifyApiJsonResponse('PATCH', $url, 404, 'admin', ['name' => 'Patched']);
    }
}

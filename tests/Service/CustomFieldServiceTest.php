<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Mapper\CustomFieldConfigMapper;
use Phorum\Mapper\UserCustomFieldMapper;
use Phorum\Model\CustomFieldConfig;
use Phorum\Model\UserCustomField;
use Phorum\Service\CustomFieldService;
use PHPUnit\Framework\TestCase;

class CustomFieldServiceTest extends TestCase
{
    private function makeConfig(int $id, string $name, int $length = 100, bool $htmlDisabled = false): CustomFieldConfig
    {
        $c                = new CustomFieldConfig();
        $c->id            = $id;
        $c->name          = $name;
        $c->length        = $length;
        $c->html_disabled = $htmlDisabled ? 1 : 0;
        return $c;
    }

    private function makeStoredField(string $data): UserCustomField
    {
        $cf       = new UserCustomField();
        $cf->data = $data;
        return $cf;
    }

    // -------------------------------------------------------------------------
    // saveUserFields() — validation
    // -------------------------------------------------------------------------

    public function testSaveUserFieldsReturnsEmptyErrorsOnValidData(): void
    {
        $config  = $this->makeConfig(1, 'bio', length: 200);
        $cfgMapper = $this->createMock(CustomFieldConfigMapper::class);
        $cfgMapper->method('findAll')->willReturn([$config]);

        $valMapper = $this->createMock(UserCustomFieldMapper::class);
        $valMapper->expects($this->once())->method('saveValue');

        $svc    = new CustomFieldService($cfgMapper, $valMapper);
        $errors = $svc->saveUserFields(1, ['bio' => 'Hello there']);
        $this->assertSame([], $errors);
    }

    public function testSaveUserFieldsReturnsErrorWhenValueTooLong(): void
    {
        $config  = $this->makeConfig(1, 'bio', length: 5);
        $cfgMapper = $this->createMock(CustomFieldConfigMapper::class);
        $cfgMapper->method('findAll')->willReturn([$config]);

        $valMapper = $this->createMock(UserCustomFieldMapper::class);
        $valMapper->expects($this->never())->method('saveValue');

        $svc    = new CustomFieldService($cfgMapper, $valMapper);
        $errors = $svc->saveUserFields(1, ['bio' => 'This string is too long']);
        $this->assertNotEmpty($errors);
    }

    public function testSaveUserFieldsSkipsFieldsNotInConfigs(): void
    {
        $cfgMapper = $this->createMock(CustomFieldConfigMapper::class);
        $cfgMapper->method('findAll')->willReturn([]);

        $valMapper = $this->createMock(UserCustomFieldMapper::class);
        $valMapper->expects($this->never())->method('saveValue');

        $svc = new CustomFieldService($cfgMapper, $valMapper);
        $svc->saveUserFields(1, ['unknown_field' => 'value']);
    }

    public function testSaveUserFieldsDryRunSkipsPersistence(): void
    {
        $config  = $this->makeConfig(1, 'bio', length: 200);
        $cfgMapper = $this->createMock(CustomFieldConfigMapper::class);
        $cfgMapper->method('findAll')->willReturn([$config]);

        $valMapper = $this->createMock(UserCustomFieldMapper::class);
        $valMapper->expects($this->never())->method('saveValue');

        $svc    = new CustomFieldService($cfgMapper, $valMapper);
        $errors = $svc->saveUserFields(1, ['bio' => 'Hello'], dryRun: true);
        $this->assertSame([], $errors);
    }

    // -------------------------------------------------------------------------
    // getUserFields() — merging configs and stored values
    // -------------------------------------------------------------------------

    public function testGetUserFieldsMergesConfigsAndStoredValues(): void
    {
        $config  = $this->makeConfig(1, 'bio');
        $stored  = [1 => $this->makeStoredField('My bio text')];

        $cfgMapper = $this->createMock(CustomFieldConfigMapper::class);
        $cfgMapper->method('findAll')->willReturn([$config]);

        $valMapper = $this->createMock(UserCustomFieldMapper::class);
        $valMapper->method('loadForUser')->willReturn($stored);

        $svc    = new CustomFieldService($cfgMapper, $valMapper);
        $fields = $svc->getUserFields(1);

        $this->assertCount(1, $fields);
        $this->assertSame('My bio text', $fields[0]['value']);
        $this->assertSame($config, $fields[0]['config']);
    }

    public function testGetUserFieldsReturnsEmptyWhenNoConfigs(): void
    {
        $cfgMapper = $this->createMock(CustomFieldConfigMapper::class);
        $cfgMapper->method('findAll')->willReturn([]);

        $valMapper = $this->createMock(UserCustomFieldMapper::class);

        $svc = new CustomFieldService($cfgMapper, $valMapper);
        $this->assertSame([], $svc->getUserFields(1));
    }

    public function testGetUserFieldsHtmlEscapesValueWhenHtmlDisabled(): void
    {
        $config = $this->makeConfig(1, 'bio', htmlDisabled: true);
        $stored = [1 => $this->makeStoredField('<script>xss</script>')];

        $cfgMapper = $this->createMock(CustomFieldConfigMapper::class);
        $cfgMapper->method('findAll')->willReturn([$config]);

        $valMapper = $this->createMock(UserCustomFieldMapper::class);
        $valMapper->method('loadForUser')->willReturn($stored);

        $svc    = new CustomFieldService($cfgMapper, $valMapper);
        $fields = $svc->getUserFields(1);

        $this->assertStringNotContainsString('<script>', $fields[0]['value']);
        $this->assertStringContainsString('&lt;script&gt;', $fields[0]['value']);
    }

    // -------------------------------------------------------------------------
    // getAdminUserFields()
    // -------------------------------------------------------------------------

    public function testGetAdminUserFieldsFiltersToShowInAdmin(): void
    {
        $shown  = $this->makeConfig(1, 'bio');
        $shown->show_in_admin = 1;
        $hidden = $this->makeConfig(2, 'internal_note');

        $cfgMapper = $this->createMock(CustomFieldConfigMapper::class);
        $cfgMapper->method('findAll')->willReturn([$shown, $hidden]);

        $valMapper = $this->createMock(UserCustomFieldMapper::class);
        $valMapper->method('loadForUser')->willReturn([]);

        $svc    = new CustomFieldService($cfgMapper, $valMapper);
        $fields = $svc->getAdminUserFields(1);

        $this->assertCount(1, $fields);
        $this->assertSame('bio', $fields[0]['config']->name);
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Mapper\CustomFieldConfigMapper;
use Phorum\Mapper\CustomFieldMapper;
use Phorum\Model\CustomField;
use Phorum\Model\CustomFieldConfig;
use Phorum\Model\Forum;
use Phorum\Model\Message;
use Phorum\Service\CustomFieldService;
use PHPUnit\Framework\TestCase;

class CustomFieldServiceTest extends TestCase
{
    private function makeConfig(int $id, string $name, int $length = 100, bool $htmlDisabled = false): CustomFieldConfig
    {
        $c               = new CustomFieldConfig();
        $c->id           = $id;
        $c->name         = $name;
        $c->length       = $length;
        $c->html_disabled = $htmlDisabled ? 1 : 0;
        $c->field_type   = CustomFieldConfig::FIELD_TYPE_USER;
        return $c;
    }

    private function makeStoredField(string $data): CustomField
    {
        $cf       = new CustomField();
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
        $cfgMapper->method('findByFieldType')->willReturn([$config]);

        $valMapper = $this->createMock(CustomFieldMapper::class);
        $valMapper->expects($this->once())->method('saveValue');

        $svc    = new CustomFieldService($cfgMapper, $valMapper);
        $errors = $svc->saveUserFields(1, ['bio' => 'Hello there']);
        $this->assertSame([], $errors);
    }

    public function testSaveUserFieldsReturnsErrorWhenValueTooLong(): void
    {
        $config  = $this->makeConfig(1, 'bio', length: 5);
        $cfgMapper = $this->createMock(CustomFieldConfigMapper::class);
        $cfgMapper->method('findByFieldType')->willReturn([$config]);

        $valMapper = $this->createMock(CustomFieldMapper::class);
        $valMapper->expects($this->never())->method('saveValue');

        $svc    = new CustomFieldService($cfgMapper, $valMapper);
        $errors = $svc->saveUserFields(1, ['bio' => 'This string is too long']);
        $this->assertNotEmpty($errors);
    }

    public function testSaveUserFieldsSkipsFieldsNotInConfigs(): void
    {
        $cfgMapper = $this->createMock(CustomFieldConfigMapper::class);
        $cfgMapper->method('findByFieldType')->willReturn([]);

        $valMapper = $this->createMock(CustomFieldMapper::class);
        $valMapper->expects($this->never())->method('saveValue');

        $svc = new CustomFieldService($cfgMapper, $valMapper);
        $svc->saveUserFields(1, ['unknown_field' => 'value']);
    }

    public function testSaveUserFieldsDryRunSkipsPersistence(): void
    {
        $config  = $this->makeConfig(1, 'bio', length: 200);
        $cfgMapper = $this->createMock(CustomFieldConfigMapper::class);
        $cfgMapper->method('findByFieldType')->willReturn([$config]);

        $valMapper = $this->createMock(CustomFieldMapper::class);
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
        $cfgMapper->method('findByFieldType')->willReturn([$config]);

        $valMapper = $this->createMock(CustomFieldMapper::class);
        $valMapper->method('loadForRelation')->willReturn($stored);

        $svc    = new CustomFieldService($cfgMapper, $valMapper);
        $fields = $svc->getUserFields(1);

        $this->assertCount(1, $fields);
        $this->assertSame('My bio text', $fields[0]['value']);
        $this->assertSame($config, $fields[0]['config']);
    }

    public function testGetUserFieldsReturnsEmptyWhenNoConfigs(): void
    {
        $cfgMapper = $this->createMock(CustomFieldConfigMapper::class);
        $cfgMapper->method('findByFieldType')->willReturn([]);

        $valMapper = $this->createMock(CustomFieldMapper::class);

        $svc = new CustomFieldService($cfgMapper, $valMapper);
        $this->assertSame([], $svc->getUserFields(1));
    }

    public function testGetUserFieldsHtmlEscapesValueWhenHtmlDisabled(): void
    {
        $config = $this->makeConfig(1, 'bio', htmlDisabled: true);
        $stored = [1 => $this->makeStoredField('<script>xss</script>')];

        $cfgMapper = $this->createMock(CustomFieldConfigMapper::class);
        $cfgMapper->method('findByFieldType')->willReturn([$config]);

        $valMapper = $this->createMock(CustomFieldMapper::class);
        $valMapper->method('loadForRelation')->willReturn($stored);

        $svc    = new CustomFieldService($cfgMapper, $valMapper);
        $fields = $svc->getUserFields(1);

        $this->assertStringNotContainsString('<script>', $fields[0]['value']);
        $this->assertStringContainsString('&lt;script&gt;', $fields[0]['value']);
    }

    // -------------------------------------------------------------------------
    // hydrateForums()
    // -------------------------------------------------------------------------

    public function testHydrateForumsSetsCustomFieldsOnForums(): void
    {
        $config              = new CustomFieldConfig();
        $config->id          = 1;
        $config->name        = 'color';
        $config->html_disabled = 0;
        $config->field_type  = CustomFieldConfig::FIELD_TYPE_FORUM;

        $cf       = new CustomField();
        $cf->data = 'blue';

        $cfgMapper = $this->createMock(CustomFieldConfigMapper::class);
        $cfgMapper->method('findByFieldType')->willReturn([$config]);

        $valMapper = $this->createMock(CustomFieldMapper::class);
        $valMapper->method('loadForRelations')->willReturn([10 => [1 => $cf]]);

        $forum          = new Forum();
        $forum->forum_id = 10;

        $svc = new CustomFieldService($cfgMapper, $valMapper);
        $svc->hydrateForums([$forum]);

        $this->assertSame(['color' => 'blue'], $forum->custom_fields);
    }

    public function testHydrateForumsDoesNothingForEmptyInput(): void
    {
        $cfgMapper = $this->createMock(CustomFieldConfigMapper::class);
        $cfgMapper->expects($this->never())->method('findByFieldType');

        $svc = new CustomFieldService($cfgMapper, $this->createMock(CustomFieldMapper::class));
        $svc->hydrateForums([]);
    }

    // -------------------------------------------------------------------------
    // hydrateMessages()
    // -------------------------------------------------------------------------

    public function testHydrateMessagesSetsCustomFieldsOnMessages(): void
    {
        $config              = new CustomFieldConfig();
        $config->id          = 2;
        $config->name        = 'mood';
        $config->html_disabled = 0;
        $config->field_type  = CustomFieldConfig::FIELD_TYPE_MESSAGE;

        $cf       = new CustomField();
        $cf->data = 'happy';

        $cfgMapper = $this->createMock(CustomFieldConfigMapper::class);
        $cfgMapper->method('findByFieldType')->willReturn([$config]);

        $valMapper = $this->createMock(CustomFieldMapper::class);
        $valMapper->method('loadForRelations')->willReturn([55 => [2 => $cf]]);

        $msg             = new Message();
        $msg->message_id = 55;

        $svc = new CustomFieldService($cfgMapper, $valMapper);
        $svc->hydrateMessages([$msg]);

        $this->assertSame(['mood' => 'happy'], $msg->custom_fields);
    }

    public function testHydrateMessagesDoesNothingForEmptyInput(): void
    {
        $cfgMapper = $this->createMock(CustomFieldConfigMapper::class);
        $cfgMapper->expects($this->never())->method('findByFieldType');

        $svc = new CustomFieldService($cfgMapper, $this->createMock(CustomFieldMapper::class));
        $svc->hydrateMessages([]);
    }
}

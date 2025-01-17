<?php

declare(strict_types=1);

namespace LaminasTest\Permissions\Rbac;

use Laminas\Permissions\Rbac;
use Laminas\Permissions\Rbac\Exception;
use Laminas\Permissions\Rbac\Role;
use PHPUnit\Framework\TestCase;
use stdClass;

class RbacTest extends TestCase
{
    protected Rbac\Rbac $rbac;

    public function setUp(): void
    {
        $this->rbac = new Rbac\Rbac();
    }

    public function testIsGrantedAssertion(): void
    {
        $foo = new Rbac\Role('foo');
        $bar = new Rbac\Role('bar');

        $true  = new TestAsset\SimpleTrueAssertion();
        $false = new TestAsset\SimpleFalseAssertion();

        $roleNoMatch = new TestAsset\RoleMustMatchAssertion($bar);
        $roleMatch   = new TestAsset\RoleMustMatchAssertion($foo);

        $foo->addPermission('can.foo');
        $bar->addPermission('can.bar');

        $this->rbac->addRole($foo);
        $this->rbac->addRole($bar);

        $this->assertEquals(true, $this->rbac->isGranted($foo, 'can.foo', $true));
        $this->assertEquals(false, $this->rbac->isGranted($bar, 'can.bar', $false));

        $this->assertEquals(false, $this->rbac->isGranted($foo, 'cannot', $true));
        $this->assertEquals(false, $this->rbac->isGranted($bar, 'cannot', $false));

        $this->assertEquals(false, $this->rbac->isGranted($bar, 'can.bar', $roleNoMatch));
        $this->assertEquals(false, $this->rbac->isGranted($bar, 'can.foo', $roleNoMatch));

        $this->assertEquals(true, $this->rbac->isGranted($foo, 'can.foo', $roleMatch));
    }

    public function testIsGrantedSingleRole(): void
    {
        $foo = new Rbac\Role('foo');
        $foo->addPermission('can.bar');

        $this->rbac->addRole($foo);

        $this->assertEquals(true, $this->rbac->isGranted('foo', 'can.bar'));
        $this->assertEquals(false, $this->rbac->isGranted('foo', 'can.baz'));
    }

    public function testIsGrantedChildRoles(): void
    {
        $foo = new Rbac\Role('foo');
        $bar = new Rbac\Role('bar');

        $foo->addPermission('can.foo');
        $bar->addPermission('can.bar');

        $this->rbac->addRole($foo);
        $this->rbac->addRole($bar, $foo);

        $this->assertEquals(true, $this->rbac->isGranted('foo', 'can.bar'));
        $this->assertEquals(true, $this->rbac->isGranted('foo', 'can.foo'));
        $this->assertEquals(true, $this->rbac->isGranted('bar', 'can.bar'));

        $this->assertEquals(false, $this->rbac->isGranted('foo', 'can.baz'));
        $this->assertEquals(false, $this->rbac->isGranted('bar', 'can.baz'));
    }

    public function testIsGrantedWithInvalidRole(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->rbac->isGranted('foo', 'permission');
    }

    public function testGetRole(): void
    {
        $foo = new Rbac\Role('foo');
        $this->rbac->addRole($foo);
        $this->assertEquals($foo, $this->rbac->getRole('foo'));
    }

    /**
     * @covers Laminas\Permissions\Rbac\Rbac::hasRole()
     */
    public function testHasRole(): void
    {
        $foo   = new Rbac\Role('foo');
        $snafu = new TestAsset\RoleTest('snafu');

        $this->rbac->addRole('bar');
        $this->rbac->addRole($foo);
        $this->rbac->addRole('snafu');

        // check that the container has the same object $foo
        $this->assertTrue($this->rbac->hasRole($foo));

        // check that the container has the same string "bar"
        $this->assertTrue($this->rbac->hasRole('bar'));

        // check that the container do not have the string "baz"
        $this->assertFalse($this->rbac->hasRole('baz'));

        // check that 'snafu' role and $snafu are different
        $this->assertNotEquals($snafu, $this->rbac->getRole('snafu'));
        $this->assertTrue($this->rbac->hasRole('snafu'));
        $this->assertFalse($this->rbac->hasRole($snafu));
    }

    public function testHasRoleWithInvalidElement(): void
    {
        $role = new stdClass();
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->rbac->hasRole($role);
    }

    public function testGetUndefinedRole(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->rbac->getRole('foo');
    }

    public function testAddRoleFromString(): void
    {
        $this->rbac->addRole('foo');

        $foo = $this->rbac->getRole('foo');
        $this->assertInstanceOf(Role::class, $foo);
    }

    public function testAddRoleFromClass(): void
    {
        $foo = new Rbac\Role('foo');

        $this->rbac->addRole('foo');
        $foo2 = $this->rbac->getRole('foo');

        $this->assertEquals($foo, $foo2);
        $this->assertInstanceOf(Role::class, $foo2);
    }

    public function testAddRoleNotValid(): void
    {
        $foo = new stdClass();
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->rbac->addRole($foo);
    }

    public function testAddRoleWithParentsUsingRbac(): void
    {
        $foo = new Rbac\Role('foo');
        $bar = new Rbac\Role('bar');

        $this->rbac->addRole($foo);
        $this->rbac->addRole($bar, $foo);

        $this->assertEquals([$foo], $bar->getParents());
        $this->assertEquals([$bar], $foo->getChildren());
    }

    public function testAddRoleWithAutomaticParentsUsingRbac(): void
    {
        $foo = new Rbac\Role('foo');
        $bar = new Rbac\Role('bar');

        $this->rbac->setCreateMissingRoles(true);
        $this->assertTrue($this->rbac->getCreateMissingRoles());
        $this->rbac->addRole($bar, $foo);

        $this->assertEquals([$foo], $bar->getParents());
        $this->assertEquals([$bar], $foo->getChildren());
    }

    /**
     * @tesdox Test adding custom child roles works
     */
    public function testAddCustomChildRole(): void
    {
        $role = $this->getMockForAbstractClass(Rbac\RoleInterface::class);
        $this->rbac->setCreateMissingRoles(true);
        $this->rbac->addRole($role, 'parent');

        $role->expects($this->any())
            ->method('getName')
            ->will($this->returnValue('customchild'));

        $role->expects($this->once())
            ->method('hasPermission')
            ->with('test')
            ->will($this->returnValue(true));

        $this->assertTrue($this->rbac->isGranted('parent', 'test'));
    }

    public function testAddMultipleParentRole(): void
    {
        $adminRole = new Rbac\Role('Administrator');
        $adminRole->addPermission('user.manage');
        $this->rbac->addRole($adminRole);

        $managerRole = new Rbac\Role('Manager');
        $managerRole->addPermission('post.publish');
        $this->rbac->addRole($managerRole, ['Administrator']);

        $editorRole = new Rbac\Role('Editor');
        $editorRole->addPermission('post.edit');
        $this->rbac->addRole($editorRole);

        $viewerRole = new Rbac\Role('Viewer');
        $viewerRole->addPermission('post.view');
        $this->rbac->addRole($viewerRole, ['Editor', 'Manager']);

        $this->assertEquals('Viewer', $editorRole->getChildren()[0]->getName());
        $this->assertEquals('Viewer', $managerRole->getChildren()[0]->getName());
        $this->assertTrue($this->rbac->isGranted('Editor', 'post.view'));
        $this->assertTrue($this->rbac->isGranted('Manager', 'post.view'));

        $this->assertEquals([$editorRole, $managerRole], $viewerRole->getParents());
        $this->assertEquals([$adminRole], $managerRole->getParents());
        $this->assertEmpty($editorRole->getParents());
        $this->assertEmpty($adminRole->getParents());
    }

    public function testAddParentRole(): void
    {
        $adminRole = new Rbac\Role('Administrator');
        $adminRole->addPermission('user.manage');
        $this->rbac->addRole($adminRole);

        $managerRole = new Rbac\Role('Manager');
        $managerRole->addPermission('post.publish');
        $managerRole->addParent($adminRole);
        $this->rbac->addRole($managerRole);

        $editorRole = new Rbac\Role('Editor');
        $editorRole->addPermission('post.edit');
        $this->rbac->addRole($editorRole);

        $viewerRole = new Rbac\Role('Viewer');
        $viewerRole->addPermission('post.view');
        $viewerRole->addParent($editorRole);
        $viewerRole->addParent($managerRole);
        $this->rbac->addRole($viewerRole);

        // Check roles hierarchy
        $this->assertEquals([$viewerRole], $editorRole->getChildren());
        $this->assertEquals([$viewerRole], $managerRole->getChildren());
        $this->assertEquals([$editorRole, $managerRole], $viewerRole->getParents());
        $this->assertEquals([$adminRole], $managerRole->getParents());
        $this->assertEmpty($editorRole->getParents());
        $this->assertEmpty($adminRole->getParents());

        // Check permissions
        $this->assertTrue($this->rbac->isGranted('Editor', 'post.view'));
        $this->assertTrue($this->rbac->isGranted('Editor', 'post.edit'));
        $this->assertTrue($this->rbac->isGranted('Viewer', 'post.view'));
        $this->assertTrue($this->rbac->isGranted('Manager', 'post.view'));
        $this->assertTrue($this->rbac->isGranted('Administrator', 'post.view'));
        $this->assertTrue($this->rbac->isGranted('Administrator', 'post.publish'));
        $this->assertFalse($this->rbac->isGranted('Administrator', 'post.edit'));
        $this->assertFalse($this->rbac->isGranted('Manager', 'post.edit'));
        $this->assertFalse($this->rbac->isGranted('Viewer', 'post.edit'));
        $this->assertFalse($this->rbac->isGranted('Viewer', 'post.publish'));
        $this->assertFalse($this->rbac->isGranted('Viewer', 'user.manage'));
        $this->assertFalse($this->rbac->isGranted('Editor', 'user.manage'));
        $this->assertFalse($this->rbac->isGranted('Editor', 'post.publish'));
        $this->assertFalse($this->rbac->isGranted('Manager', 'user.manage'));
    }

    public function testGetRoles(): void
    {
        $adminRole = new Rbac\Role('Administrator');
        $adminRole->addPermission('user.manage');
        $this->rbac->addRole($adminRole);

        $managerRole = new Rbac\Role('Manager');
        $managerRole->addPermission('post.publish');
        $managerRole->addParent($adminRole);
        $this->rbac->addRole($managerRole);

        $this->assertEquals([$adminRole, $managerRole], $this->rbac->getRoles());
    }

    public function testEmptyRoles(): void
    {
        $this->assertEquals([], $this->rbac->getRoles());
    }
}

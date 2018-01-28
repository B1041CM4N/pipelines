<?php

/* this file is part of pipelines */

namespace Ktomk\Pipelines;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Ktomk\Pipelines\Lib
 */
class LibTest extends TestCase
{
    public function testGenerateUuid()
    {
        $actual = Lib::generateUuid();
        $pattern = '/^\{?[0-9a-f]{8}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?' .
            '[0-9a-f]{4}\-?[0-9a-f]{12}\}?$/i';
        $this->assertRegExp($pattern, $actual);
    }

    public function testCmd()
    {
        $actual = Lib::cmd('foo', array('bar', 'baz'));
        $expected = 'foo bar baz';
        $this->assertInternalType('string', $actual);
        $this->assertEquals($actual, $expected);
    }

    public function testCmdArgumentMerging()
    {
        $actual = Lib::cmd('cmd', array('-a', array('-b', 'c')));
        $expected = 'cmd -a -b c';
        $this->assertSame($actual, $expected);
    }

    public function provideQuoteArgs()
    {
        return array(
            array("abc", "abc"),
            array(" ", "' '"),
            array("'", "\\'"),
            array("''", "\\'\\'"),
            array("sally's o'hara", "sally\\''s o'\\'hara"),
            array("", "''"),
        );
    }

    /**
     * @dataProvider provideQuoteArgs
     */
    public function testQuoteArg($argument, $expected)
    {
        $actual = Lib::quoteArg($argument);
        $this->assertSame($expected, $actual);
    }

    /**
     *
     */
    public function testLines()
    {
        $atEnd = "1\n2\n3\n";

        $lines = Lib::lines($atEnd);

        $this->assertEquals(array(1, 2, 3), $lines);
    }

    public function testMerge()
    {
        $this->assertEquals(array(1,3,4), Lib::merge(1, array(3,4)));
    }

    public function testMergeEmpty()
    {
        $this->assertEquals(array(), Lib::merge());
    }

    public function provideBracePattern()
    {
        return array(
            array(array(""), ""),
            array(array("{}"), "{}"),
            'no duplicates' => array(array(""), "{,}"),
            'no duplicates 2' => array(array("ab"), "a{,}b"),
            array(array("acb", "adb"), "a{c,d}b"),
            'hangover left' => array(array("a{cb", "a{db"), "a{{c,d}b"),
            'hangover right' => array(array("ac}b", "ad}b"), "a{c,d}}b"),
            array(array("abe", "ace", "ade"), "a{b,{c,d}}e"),
            'brace' => array(array("ab", "ac"), "a{b,c}"),
            'escaped brace' => array(array("a{b,c}"), "a\{b,c}"),
            'escaped comma' => array(array("a,", "ab"), "a{\,,b}"),
            'multiple' => array(
                array('abdh', 'abefh', 'abgh', 'abcdh', 'abcefh', 'abcgh'),
                "ab{,c}{d,{ef,g}}h"
            )
        );
    }

    /**
     * @param $subject
     * @param $expected
     * @dataProvider provideBracePattern
     */
    public function testExpandBrace($expected, $subject)
    {
        $this->assertSame($expected, Lib::expandBrace($subject), $subject);
    }

    public function provideAbsolutePaths() {
        return array(
            array('foo.txt', false),
            array('', false),
            array('/', true),
            array('//', false),
            array('///', true),
            array('/foo.txt', true),
            array('bar/foo.txt', false),
            array('/bar/foo.txt', true),
        );
    }

    /**
     * @dataProvider provideAbsolutePaths
     * @param string $path
     * @param bool $expected
     */
    public function testFsIsAbsolutePath($path, $expected)
    {
        $actual = Lib::fsIsAbsolutePath($path);
        $this->assertSame($expected, $actual, "path '$path' is (not) absolute");

    }

    public function provideBasenamePaths() {
        return array(
            array('foo.txt', true),
            array('', false),
            array('/', false),
            array('/foo.txt', false),
            array('bar/foo.txt', false),
        );
    }
    /**
     * @dataProvider provideBasenamePaths
     * @param string $path
     * @param bool $expected
     */
    public function testFsIsBasename($path, $expected)
    {
        $actual = Lib::fsIsBasename($path);
        $this->assertSame($expected, $actual, 'path is (not) basename');
    }
}

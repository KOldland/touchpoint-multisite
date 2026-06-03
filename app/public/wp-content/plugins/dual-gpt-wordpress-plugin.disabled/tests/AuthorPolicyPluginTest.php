<?php

use PHPUnit\Framework\TestCase;

class AuthorPolicyPluginTest extends TestCase
{
    public function test_sanitize_author_policy_normalizes_ranges_and_phrases()
    {
        $plugin = new Dual_GPT_Plugin();
        $method = new ReflectionMethod(Dual_GPT_Plugin::class, 'sanitize_author_policy');
        $method->setAccessible(true);

        $sanitized = $method->invoke($plugin, array(
            'min_words' => 120,
            'max_words' => 500,
            'banned_phrases' => array('  AI Hype  ', 'ai hype', '', 'Overpromise  '),
            'disallow_first_person' => 0,
        ));

        $this->assertSame(300, $sanitized['min_words']);
        $this->assertSame(500, $sanitized['max_words']);
        $this->assertSame(array('ai hype', 'overpromise'), $sanitized['banned_phrases']);
        $this->assertFalse($sanitized['disallow_first_person']);
    }

    public function test_resolve_author_policy_falls_back_to_persona_policy()
    {
        $plugin = new Dual_GPT_Plugin();
        $method = new ReflectionMethod(Dual_GPT_Plugin::class, 'resolve_author_policy');
        $method->setAccessible(true);

        $resolved = $method->invoke($plugin, array(
            'persona_policy' => array(
                'author' => array(
                    'min_words' => 900,
                    'max_words' => 1400,
                    'disallow_em_dash' => false,
                    'banned_phrases' => array('CTA'),
                ),
            ),
        ));

        $this->assertSame(900, $resolved['min_words']);
        $this->assertSame(1400, $resolved['max_words']);
        $this->assertFalse($resolved['disallow_em_dash']);
        $this->assertSame(array('cta'), $resolved['banned_phrases']);
    }
}

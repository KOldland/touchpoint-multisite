<?php

use PHPUnit\Framework\TestCase;

class AuthorAgentPolicyConstraintsTest extends TestCase
{
    public function test_validate_draft_constraints_flags_policy_violations()
    {
        $agent = new Dual_GPT_Author_Agent();
        $method = new ReflectionMethod(Dual_GPT_Author_Agent::class, 'validate_draft_constraints');
        $method->setAccessible(true);

        $blocks = array(
            array('type' => 'paragraph', 'content' => 'I think this market is not stable -- but we can solve it in 10 ways.'),
            array('type' => 'paragraph', 'content' => 'In conclusion, this is the final thought.'),
        );

        $policy = array(
            'disallow_first_person' => true,
            'disallow_em_dash' => true,
            'disallow_rhetorical_binaries' => true,
            'disallow_listicle_framing' => true,
            'disallow_tidy_conclusion' => true,
            'min_words' => 300,
            'max_words' => 400,
            'banned_phrases' => array('final thought'),
        );

        $result = $method->invoke($agent, $blocks, array('brand_profile' => 'Brand A (FSI)'), $policy);
        $warnings = $result['warnings'];

        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('First-person perspective detected', implode(' | ', $warnings));
        $this->assertStringContainsString('Em dash usage violates policy', implode(' | ', $warnings));
        $this->assertStringContainsString('Banned phrase detected: final thought', implode(' | ', $warnings));
    }

    public function test_validate_draft_constraints_respects_disabled_toggles()
    {
        $agent = new Dual_GPT_Author_Agent();
        $method = new ReflectionMethod(Dual_GPT_Author_Agent::class, 'validate_draft_constraints');
        $method->setAccessible(true);

        $blocks = array(
            array('type' => 'paragraph', 'content' => 'I think we should ship this -- not perfect, but practical.'),
            array('type' => 'paragraph', 'content' => 'Top 5 ways to improve adoption are listed below.'),
        );

        $policy = array(
            'disallow_first_person' => false,
            'disallow_em_dash' => false,
            'disallow_rhetorical_binaries' => false,
            'disallow_listicle_framing' => false,
            'disallow_tidy_conclusion' => false,
            'min_words' => 1,
            'max_words' => 5000,
            'banned_phrases' => array(),
        );

        $result = $method->invoke($agent, $blocks, array('brand_profile' => 'Brand B (Enterprise)'), $policy);
        $warnings = implode(' | ', $result['warnings']);

        $this->assertStringNotContainsString('First-person perspective detected', $warnings);
        $this->assertStringNotContainsString('Em dash usage violates policy', $warnings);
        $this->assertStringNotContainsString('Rhetorical binary detected', $warnings);
        $this->assertStringNotContainsString('Listicle-style framing detected', $warnings);
    }
}

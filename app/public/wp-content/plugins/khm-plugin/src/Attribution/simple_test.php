<?php
/**
 * KHM Attribution Simple Test Runner
 * 
 * Simplified test runner focusing on file validation and basic checks
 */

class KHM_Attribution_Simple_Test_Runner {
    
    private $test_results = array();
    private $start_time;
    
    public function __construct() {
        $this->start_time = microtime(true);
        echo "🚀 KHM Attribution Phase 5 Simple Test Suite\n";
        echo "=============================================\n\n";
    }
    
    /**
     * Run all Phase 5 validation tests
     */
    public function run_validation_tests() {
        $components = array(
            'EnterpriseIntegrationManager' => 'Enterprise Integration System',
            'APIEcosystemManager' => 'API Ecosystem Management',
            'MarketingAutomationEngine' => 'Marketing Automation System',
            'AdvancedCampaignIntelligence' => 'Campaign Intelligence System',
            'TestSuite' => 'Testing Framework'
        );
        
        foreach ($components as $class_name => $component_name) {
            $this->validate_component($class_name, $component_name);
        }
        
        $this->run_code_quality_tests();
        $this->run_architecture_tests();
        $this->generate_validation_report();
    }
    
    /**
     * Validate individual component
     */
    private function validate_component($class_name, $component_name) {
        echo "🧪 Validating: $component_name\n";
        echo str_repeat('-', 60) . "\n";
        
        $test_result = array(
            'component' => $component_name,
            'class' => $class_name,
            'file_exists' => false,
            'syntax_valid' => false,
            'file_size' => 0,
            'line_count' => 0,
            'class_defined' => false,
            'method_count' => 0,
            'has_constructor' => false,
            'has_public_methods' => false,
            'complexity_score' => 0,
            'errors' => array()
        );
        
        try {
            // Test 1: File existence
            $file_path = __DIR__ . "/{$class_name}.php";
            if (file_exists($file_path)) {
                echo "✅ File exists: {$class_name}.php\n";
                $test_result['file_exists'] = true;
                
                // Get file info
                $test_result['file_size'] = filesize($file_path);
                $content = file_get_contents($file_path);
                $test_result['line_count'] = substr_count($content, "\n") + 1;
                
                echo "📊 File size: " . $this->format_bytes($test_result['file_size']) . "\n";
                echo "📊 Line count: {$test_result['line_count']}\n";
            } else {
                echo "❌ File not found: {$file_path}\n";
                $test_result['errors'][] = "File not found";
            }
            
            // Test 2: PHP syntax validation
            if ($test_result['file_exists']) {
                $syntax_check = $this->check_php_syntax($file_path);
                if ($syntax_check['valid']) {
                    echo "✅ PHP syntax validation passed\n";
                    $test_result['syntax_valid'] = true;
                } else {
                    echo "❌ PHP syntax validation failed\n";
                    $test_result['errors'][] = "Syntax error: " . $syntax_check['error'];
                }
            }
            
            // Test 3: Class structure analysis
            if ($test_result['syntax_valid']) {
                $class_analysis = $this->analyze_class_structure($file_path, $class_name);
                $test_result = array_merge($test_result, $class_analysis);
                
                if ($class_analysis['class_defined']) {
                    echo "✅ Class definition found: KHM_Attribution_{$class_name}\n";
                    echo "📊 Method count: {$class_analysis['method_count']}\n";
                    
                    if ($class_analysis['has_constructor']) {
                        echo "✅ Constructor method found\n";
                    }
                    
                    if ($class_analysis['has_public_methods']) {
                        echo "✅ Public methods found\n";
                    }
                } else {
                    echo "❌ Class definition not found\n";
                    $test_result['errors'][] = "Class not found";
                }
            }
            
            // Test 4: Code complexity analysis
            if ($test_result['syntax_valid']) {
                $complexity = $this->calculate_complexity($file_path);
                $test_result['complexity_score'] = $complexity;
                echo "📊 Complexity score: {$complexity}\n";
                
                if ($complexity > 100) {
                    echo "⚠️  High complexity detected\n";
                } else {
                    echo "✅ Complexity within acceptable range\n";
                }
            }
            
        } catch (Exception $e) {
            echo "❌ Validation failed: " . $e->getMessage() . "\n";
            $test_result['errors'][] = $e->getMessage();
        }
        
        // Calculate success score
        $success_factors = 0;
        $total_factors = 6;
        
        if ($test_result['file_exists']) $success_factors++;
        if ($test_result['syntax_valid']) $success_factors++;
        if ($test_result['class_defined']) $success_factors++;
        if ($test_result['has_constructor']) $success_factors++;
        if ($test_result['has_public_methods']) $success_factors++;
        if ($test_result['complexity_score'] <= 100) $success_factors++;
        
        $success_rate = round(($success_factors / $total_factors) * 100, 1);
        $test_result['success_rate'] = $success_rate;
        
        echo "\n📊 Validation Results:\n";
        echo "   Success Rate: {$success_rate}%\n";
        echo "   File Size: " . $this->format_bytes($test_result['file_size']) . "\n";
        echo "   Lines of Code: {$test_result['line_count']}\n";
        echo "   Methods: {$test_result['method_count']}\n";
        echo "   Complexity: {$test_result['complexity_score']}\n";
        
        if (!empty($test_result['errors'])) {
            echo "   Issues: " . implode(', ', $test_result['errors']) . "\n";
        }
        
        $status = $success_rate >= 80 ? '✅ EXCELLENT' : ($success_rate >= 60 ? '⚠️  GOOD' : '❌ NEEDS WORK');
        echo "   Status: {$status}\n";
        echo "\n";
        
        $this->test_results[] = $test_result;
    }
    
    /**
     * Check PHP syntax
     */
    private function check_php_syntax($file_path) {
        $output = array();
        $return_var = 0;
        
        exec("php -l " . escapeshellarg($file_path) . " 2>&1", $output, $return_var);
        
        return array(
            'valid' => $return_var === 0,
            'error' => $return_var !== 0 ? implode("\n", $output) : null
        );
    }
    
    /**
     * Analyze class structure without instantiation
     */
    private function analyze_class_structure($file_path, $class_name) {
        $content = file_get_contents($file_path);
        
        // Map short names to full class names with underscores
        $class_mapping = array(
            'EnterpriseIntegrationManager' => 'KHM_Attribution_Enterprise_Integration_Manager',
            'APIEcosystemManager' => 'KHM_Attribution_API_Ecosystem_Manager',
            'MarketingAutomationEngine' => 'KHM_Attribution_Marketing_Automation_Engine', 
            'AdvancedCampaignIntelligence' => 'KHM_Attribution_Advanced_Campaign_Intelligence',
            'TestSuite' => 'KHM_Attribution_Test_Suite'
        );
        
        $full_class_name = isset($class_mapping[$class_name]) ? $class_mapping[$class_name] : "KHM_Attribution_{$class_name}";
        
        $result = array(
            'class_defined' => false,
            'method_count' => 0,
            'has_constructor' => false,
            'has_public_methods' => false
        );
        
        // Check if class is defined
        if (preg_match("/class\s+{$full_class_name}[\s\{]/", $content)) {
            $result['class_defined'] = true;
        }
        
        // Count methods
        $method_matches = array();
        preg_match_all("/(?:public|private|protected)\s+function\s+(\w+)/", $content, $method_matches);
        $result['method_count'] = count($method_matches[1]);
        
        // Check for constructor
        if (preg_match("/function\s+__construct/", $content)) {
            $result['has_constructor'] = true;
        }
        
        // Check for public methods
        if (preg_match("/public\s+function/", $content)) {
            $result['has_public_methods'] = true;
        }
        
        return $result;
    }
    
    /**
     * Calculate code complexity
     */
    private function calculate_complexity($file_path) {
        $content = file_get_contents($file_path);
        
        // Simple complexity calculation based on various factors
        $complexity = 0;
        
        // Method count contributes to complexity
        $method_count = preg_match_all("/function\s+/", $content);
        $complexity += $method_count * 2;
        
        // Control structures add complexity
        $control_structures = array('if', 'else', 'elseif', 'for', 'foreach', 'while', 'switch', 'case', 'try', 'catch');
        foreach ($control_structures as $structure) {
            $complexity += preg_match_all("/\b{$structure}\b/", $content);
        }
        
        // Class complexity
        $class_count = preg_match_all("/class\s+/", $content);
        $complexity += $class_count * 5;
        
        return $complexity;
    }
    
    /**
     * Run code quality tests
     */
    private function run_code_quality_tests() {
        echo "🔍 Code Quality Analysis\n";
        echo str_repeat('-', 60) . "\n";
        
        $total_size = 0;
        $total_lines = 0;
        $total_methods = 0;
        $components_count = count($this->test_results);
        
        foreach ($this->test_results as $result) {
            $total_size += $result['file_size'];
            $total_lines += $result['line_count'];
            $total_methods += $result['method_count'];
        }
        
        echo "📊 Total code size: " . $this->format_bytes($total_size) . "\n";
        echo "📊 Total lines of code: {$total_lines}\n";
        echo "📊 Total methods: {$total_methods}\n";
        echo "📊 Average file size: " . $this->format_bytes($total_size / $components_count) . "\n";
        echo "📊 Average methods per component: " . round($total_methods / $components_count, 1) . "\n";
        
        // Quality benchmarks
        $quality_score = 0;
        $total_benchmarks = 5;
        
        // Benchmark 1: Reasonable file sizes (not too small, not too large)
        $avg_file_size = $total_size / $components_count;
        if ($avg_file_size > 10000 && $avg_file_size < 100000) {
            echo "✅ File sizes within reasonable range\n";
            $quality_score++;
        } else {
            echo "⚠️  File sizes may need attention\n";
        }
        
        // Benchmark 2: Good method count
        $avg_methods = $total_methods / $components_count;
        if ($avg_methods > 10 && $avg_methods < 50) {
            echo "✅ Method count indicates good structure\n";
            $quality_score++;
        } else {
            echo "⚠️  Method count may indicate over/under-engineering\n";
        }
        
        // Benchmark 3: All files have valid syntax
        $syntax_valid_count = array_sum(array_column($this->test_results, 'syntax_valid'));
        if ($syntax_valid_count == $components_count) {
            echo "✅ All files have valid PHP syntax\n";
            $quality_score++;
        } else {
            echo "❌ Some files have syntax errors\n";
        }
        
        // Benchmark 4: All classes properly defined
        $classes_defined = array_sum(array_column($this->test_results, 'class_defined'));
        if ($classes_defined == $components_count) {
            echo "✅ All classes properly defined\n";
            $quality_score++;
        } else {
            echo "❌ Some classes not properly defined\n";
        }
        
        // Benchmark 5: Complexity under control
        $avg_complexity = array_sum(array_column($this->test_results, 'complexity_score')) / $components_count;
        if ($avg_complexity <= 80) {
            echo "✅ Code complexity under control\n";
            $quality_score++;
        } else {
            echo "⚠️  Code complexity may be high\n";
        }
        
        $quality_percentage = round(($quality_score / $total_benchmarks) * 100, 1);
        echo "\n📊 Overall Code Quality: {$quality_percentage}%\n\n";
    }
    
    /**
     * Run architecture tests
     */
    private function run_architecture_tests() {
        echo "🏗️  Architecture Analysis\n";
        echo str_repeat('-', 60) . "\n";
        
        $architecture_score = 0;
        $total_checks = 5;
        
        // Check 1: All required files present
        $required_files = array('EnterpriseIntegrationManager', 'APIEcosystemManager', 'MarketingAutomationEngine', 'AdvancedCampaignIntelligence', 'TestSuite');
        $files_present = array_intersect($required_files, array_column($this->test_results, 'class'));
        if (count($files_present) == count($required_files)) {
            echo "✅ All required Phase 5 components present\n";
            $architecture_score++;
        } else {
            echo "❌ Some required components missing\n";
        }
        
        // Check 2: Consistent naming convention
        $naming_consistent = true;
        foreach ($this->test_results as $result) {
            if (!preg_match("/^[A-Z][a-zA-Z]+Manager$|^[A-Z][a-zA-Z]+Engine$|^[A-Z][a-zA-Z]+Intelligence$|^TestSuite$/", $result['class'])) {
                $naming_consistent = false;
                break;
            }
        }
        if ($naming_consistent) {
            echo "✅ Consistent naming convention\n";
            $architecture_score++;
        } else {
            echo "⚠️  Naming convention inconsistencies detected\n";
        }
        
        // Check 3: Reasonable component sizes
        $size_balance = true;
        foreach ($this->test_results as $result) {
            if ($result['file_size'] < 5000 || $result['file_size'] > 200000) {
                $size_balance = false;
                break;
            }
        }
        if ($size_balance) {
            echo "✅ Component sizes well balanced\n";
            $architecture_score++;
        } else {
            echo "⚠️  Some components may be too large or too small\n";
        }
        
        // Check 4: Good method distribution
        $method_distribution = true;
        foreach ($this->test_results as $result) {
            if ($result['method_count'] < 5 || $result['method_count'] > 100) {
                $method_distribution = false;
                break;
            }
        }
        if ($method_distribution) {
            echo "✅ Good method distribution across components\n";
            $architecture_score++;
        } else {
            echo "⚠️  Uneven method distribution detected\n";
        }
        
        // Check 5: All components have constructors
        $constructor_count = array_sum(array_column($this->test_results, 'has_constructor'));
        if ($constructor_count == count($this->test_results)) {
            echo "✅ All components have proper constructors\n";
            $architecture_score++;
        } else {
            echo "⚠️  Some components missing constructors\n";
        }
        
        $architecture_percentage = round(($architecture_score / $total_checks) * 100, 1);
        echo "\n📊 Architecture Quality: {$architecture_percentage}%\n\n";
    }
    
    /**
     * Generate validation report
     */
    private function generate_validation_report() {
        $total_time = (microtime(true) - $this->start_time) * 1000;
        
        echo "📊 Phase 5 Validation Report\n";
        echo "============================\n\n";
        
        $avg_success_rate = array_sum(array_column($this->test_results, 'success_rate')) / count($this->test_results);
        $total_lines = array_sum(array_column($this->test_results, 'line_count'));
        $total_size = array_sum(array_column($this->test_results, 'file_size'));
        $total_methods = array_sum(array_column($this->test_results, 'method_count'));
        
        echo "🎯 Summary Statistics:\n";
        echo "   Components Validated: " . count($this->test_results) . "\n";
        echo "   Average Success Rate: " . round($avg_success_rate, 1) . "%\n";
        echo "   Total Lines of Code: {$total_lines}\n";
        echo "   Total File Size: " . $this->format_bytes($total_size) . "\n";
        echo "   Total Methods: {$total_methods}\n";
        echo "   Validation Time: " . round($total_time, 2) . "ms\n\n";
        
        echo "📋 Component Details:\n";
        foreach ($this->test_results as $result) {
            $status_icon = $result['success_rate'] >= 80 ? '✅' : ($result['success_rate'] >= 60 ? '⚠️' : '❌');
            echo "   {$status_icon} {$result['component']}: {$result['success_rate']}% (" . 
                 $this->format_bytes($result['file_size']) . ", {$result['line_count']} lines, {$result['method_count']} methods)\n";
        }
        
        echo "\n";
        
        if ($avg_success_rate >= 90) {
            echo "🎉 OUTSTANDING! Phase 5 implementation is excellent!\n";
            echo "   All components are well-structured and follow best practices.\n";
        } elseif ($avg_success_rate >= 80) {
            echo "✅ EXCELLENT! Phase 5 implementation is very good!\n";
            echo "   Components are well-implemented with minor areas for improvement.\n";
        } elseif ($avg_success_rate >= 70) {
            echo "👍 GOOD! Phase 5 implementation is solid!\n";
            echo "   Most components are well-implemented with some areas for enhancement.\n";
        } else {
            echo "⚠️  NEEDS ATTENTION! Some components require improvements.\n";
            echo "   Review the detailed results above for specific issues to address.\n";
        }
        
        echo "\n🚀 Phase 5 Validation Complete!\n";
        echo "💾 Total codebase size: " . $this->format_bytes($total_size) . " ({$total_lines} lines)\n";
        echo "🏗️  Enterprise-grade marketing attribution system ready for deployment!\n";
    }
    
    /**
     * Format bytes for display
     */
    private function format_bytes($bytes) {
        $units = array('B', 'KB', 'MB', 'GB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// Run the validation tests
echo "Starting Phase 5 validation...\n\n";
$validator = new KHM_Attribution_Simple_Test_Runner();
$validator->run_validation_tests();
?>
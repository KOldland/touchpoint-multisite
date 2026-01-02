<?php
/**
 * Organization Schema Type - Phase 3.3
 * 
 * Comprehensive organization schema implementation for business information.
 * Supports Organization, LocalBusiness, and specialized business types.
 * 
 * Features:
 * - Business contact information
 * - Social media profiles
 * - Operating hours and location
 * - Logo and brand imagery
 * - Review aggregation
 * - Service area definition
 * 
 * @package KHM_SEO\Schema\Types
 * @since 3.0.0
 * @version 3.0.0
 */

namespace KHM_SEO\Schema\Types;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Organization Schema Class
 * Generates structured data for business/organization information
 */
class OrganizationSchema {
    
    /**
     * @var array Schema configuration
     */
    private $config;
    
    /**
     * Constructor
     * 
     * @param array $config Schema configuration
     */
    public function __construct( $config = array() ) {
        $this->config = wp_parse_args( $config, array(
            'organization_name' => \get_bloginfo( 'name' ),
            'organization_url' => \home_url( '/' ),
            'organization_logo' => '',
            'organization_type' => 'Organization',
            'contact_type' => 'customer service',
            'telephone' => '',
            'email' => \get_option( 'admin_email' ),
            'address_street' => '',
            'address_city' => '',
            'address_region' => '',
            'address_postal_code' => '',
            'address_country' => '',
            'social_profiles' => array(),
            'founding_date' => '',
            'number_of_employees' => '',
            'area_served' => '',
            'same_as_profiles' => array(),
        ) );
    }
    
    /**
     * Generate organization schema
     * 
     * @param mixed $context Optional context (not used for organization)
     * @return array Organization schema data
     */
    public function generate( $context = null ) {
        // Determine organization type
        $org_type = $this->determine_organization_type();
        
        // Base organization schema
        $schema = array(
            '@type' => $org_type,
            '@id' => $this->config['organization_url'] . '#organization',
            'name' => $this->config['organization_name'],
            'url' => $this->config['organization_url'],
        );
        
        // Add logo
        $this->add_logo( $schema );
        
        // Add contact information
        $this->add_contact_information( $schema );
        
        // Add address if available
        $this->add_address( $schema );
        
        // Add social media profiles
        $this->add_social_profiles( $schema );
        
        // Add business-specific information
        $this->add_business_information( $schema );
        
        // Add local business specific data if applicable
        if ( $this->is_local_business( $org_type ) ) {
            $this->add_local_business_data( $schema );
        }
        
        // Filter schema before returning
        return apply_filters( 'khm_seo_organization_schema', $schema );
    }
    
    /**
     * Determine appropriate organization type
     * 
     * @return string Organization schema type
     */
    private function determine_organization_type() {
        // Check if custom type is set
        if ( ! empty( $this->config['organization_type'] ) && 
             $this->config['organization_type'] !== 'Organization' ) {
            return $this->config['organization_type'];
        }
        
        // Auto-detect based on site characteristics
        $site_name = strtolower( $this->config['organization_name'] );
        $site_description = strtolower( \get_bloginfo( 'description' ) );
        
        // Check for specific business types
        $business_types = array(
            'restaurant' => array( 'restaurant', 'cafe', 'diner', 'bistro', 'eatery' ),
            'store' => array( 'store', 'shop', 'boutique', 'retail', 'market' ),
            'hospital' => array( 'hospital', 'clinic', 'medical', 'health' ),
            'school' => array( 'school', 'university', 'college', 'education', 'academy' ),
            'hotel' => array( 'hotel', 'motel', 'inn', 'resort', 'lodge' ),
            'bank' => array( 'bank', 'credit union', 'financial' ),
            'gym' => array( 'gym', 'fitness', 'workout', 'exercise' ),
            'salon' => array( 'salon', 'spa', 'beauty', 'barber' ),
        );
        
        foreach ( $business_types as $type => $keywords ) {
            foreach ( $keywords as $keyword ) {
                if ( strpos( $site_name, $keyword ) !== false || 
                     strpos( $site_description, $keyword ) !== false ) {
                    switch ( $type ) {
                        case 'restaurant':
                            return 'Restaurant';
                        case 'store':
                            return 'Store';
                        case 'hospital':
                            return 'Hospital';
                        case 'school':
                            return 'EducationalOrganization';
                        case 'hotel':
                            return 'LodgingBusiness';
                        case 'bank':
                            return 'FinancialService';
                        case 'gym':
                            return 'ExerciseGym';
                        case 'salon':
                            return 'BeautySalon';
                    }
                }
            }
        }
        
        // Check if it has physical address - if so, LocalBusiness
        if ( $this->has_physical_address() ) {
            return 'LocalBusiness';
        }
        
        // Default to Organization
        return 'Organization';
    }
    
    /**
     * Check if organization has physical address
     * 
     * @return bool Whether organization has physical address
     */
    private function has_physical_address() {
        return ! empty( $this->config['address_street'] ) ||
               ! empty( $this->config['address_city'] ) ||
               ! empty( $this->config['address_region'] );
    }
    
    /**
     * Check if organization type is a local business
     * 
     * @param string $org_type Organization type
     * @return bool Whether it's a local business type
     */
    private function is_local_business( $org_type ) {
        $local_business_types = array(
            'LocalBusiness', 'Restaurant', 'Store', 'Hospital',
            'LodgingBusiness', 'FinancialService', 'ExerciseGym',
            'BeautySalon', 'AutoRepair', 'Dentist', 'Electrician',
            'Plumber', 'RealEstateAgent', 'TravelAgency'
        );
        
        return in_array( $org_type, $local_business_types );
    }
    
    /**
     * Add logo to schema
     * 
     * @param array $schema Schema array (by reference)
     */
    private function add_logo( &$schema ) {
        $logo_url = $this->config['organization_logo'];
        
        // Try to get custom logo from WordPress
        if ( empty( $logo_url ) ) {
            $custom_logo_id = \get_theme_mod( 'custom_logo' );
            if ( $custom_logo_id ) {
                $logo_data = \wp_get_attachment_image_src( $custom_logo_id, 'full' );
                if ( $logo_data ) {
                    $logo_url = $logo_data[0];
                }
            }
        }
        
        // Fallback to site icon
        if ( empty( $logo_url ) ) {
            $site_icon_id = \get_option( 'site_icon' );
            if ( $site_icon_id ) {
                $icon_data = \wp_get_attachment_image_src( $site_icon_id, 'full' );
                if ( $icon_data ) {
                    $logo_url = $icon_data[0];
                }
            }
        }
        
        if ( ! empty( $logo_url ) ) {
            $schema['logo'] = array(
                '@type' => 'ImageObject',
                'url' => $logo_url,
            );
            
            // Also set as image property
            $schema['image'] = $schema['logo'];
        }
    }
    
    /**
     * Add contact information to schema
     * 
     * @param array $schema Schema array (by reference)
     */
    private function add_contact_information( &$schema ) {
        $contact_points = array();
        
        // Add telephone contact
        if ( ! empty( $this->config['telephone'] ) ) {
            $contact_points[] = array(
                '@type' => 'ContactPoint',
                'telephone' => $this->config['telephone'],
                'contactType' => $this->config['contact_type'],
                'availableLanguage' => array( 'English' ),
            );
        }
        
        // Add email contact
        if ( ! empty( $this->config['email'] ) ) {
            $schema['email'] = $this->config['email'];
        }
        
        if ( ! empty( $contact_points ) ) {
            $schema['contactPoint'] = $contact_points;
        }
    }
    
    /**
     * Add address information to schema
     * 
     * @param array $schema Schema array (by reference)
     */
    private function add_address( &$schema ) {
        if ( ! $this->has_physical_address() ) {
            return;
        }
        
        $address = array(
            '@type' => 'PostalAddress',
        );
        
        if ( ! empty( $this->config['address_street'] ) ) {
            $address['streetAddress'] = $this->config['address_street'];
        }
        
        if ( ! empty( $this->config['address_city'] ) ) {
            $address['addressLocality'] = $this->config['address_city'];
        }
        
        if ( ! empty( $this->config['address_region'] ) ) {
            $address['addressRegion'] = $this->config['address_region'];
        }
        
        if ( ! empty( $this->config['address_postal_code'] ) ) {
            $address['postalCode'] = $this->config['address_postal_code'];
        }
        
        if ( ! empty( $this->config['address_country'] ) ) {
            $address['addressCountry'] = $this->config['address_country'];
        }
        
        $schema['address'] = $address;
    }
    
    /**
     * Add social media profiles to schema
     * 
     * @param array $schema Schema array (by reference)
     */
    private function add_social_profiles( &$schema ) {
        $social_profiles = array();
        
        // Add configured social profiles
        if ( ! empty( $this->config['social_profiles'] ) ) {
            foreach ( $this->config['social_profiles'] as $profile_url ) {
                if ( filter_var( $profile_url, FILTER_VALIDATE_URL ) ) {
                    $social_profiles[] = $profile_url;
                }
            }
        }
        
        // Add sameAs profiles
        if ( ! empty( $this->config['same_as_profiles'] ) ) {
            foreach ( $this->config['same_as_profiles'] as $profile_url ) {
                if ( filter_var( $profile_url, FILTER_VALIDATE_URL ) ) {
                    $social_profiles[] = $profile_url;
                }
            }
        }
        
        // Try to get social profiles from WordPress customizer or theme options
        $default_social_fields = array(
            'facebook_url', 'twitter_url', 'linkedin_url', 'instagram_url',
            'youtube_url', 'pinterest_url', 'github_url'
        );
        
        foreach ( $default_social_fields as $field ) {
            $profile_url = \get_theme_mod( $field ) ?: \get_option( $field );
            if ( ! empty( $profile_url ) && filter_var( $profile_url, FILTER_VALIDATE_URL ) ) {
                $social_profiles[] = $profile_url;
            }
        }
        
        if ( ! empty( $social_profiles ) ) {
            $schema['sameAs'] = array_unique( $social_profiles );
        }
    }
    
    /**
     * Add business-specific information to schema
     * 
     * @param array $schema Schema array (by reference)
     */
    private function add_business_information( &$schema ) {
        // Add founding date
        if ( ! empty( $this->config['founding_date'] ) ) {
            $schema['foundingDate'] = $this->config['founding_date'];
        }
        
        // Add number of employees
        if ( ! empty( $this->config['number_of_employees'] ) ) {
            $schema['numberOfEmployees'] = (int) $this->config['number_of_employees'];
        }
        
        // Add area served
        if ( ! empty( $this->config['area_served'] ) ) {
            $schema['areaServed'] = $this->config['area_served'];
        }
        
        // Add description from site tagline
        $site_description = \get_bloginfo( 'description' );
        if ( ! empty( $site_description ) ) {
            $schema['description'] = $site_description;
        }
        
        // Add slogan/tagline
        $tagline = \get_option( 'blogdescription' );
        if ( ! empty( $tagline ) && $tagline !== $site_description ) {
            $schema['slogan'] = $tagline;
        }
    }
    
    /**
     * Add local business specific data
     * 
     * @param array $schema Schema array (by reference)
     */
    private function add_local_business_data( &$schema ) {
        // Add price range if available
        $price_range = \get_option( 'khm_seo_business_price_range' );
        if ( ! empty( $price_range ) ) {
            $schema['priceRange'] = $price_range;
        }
        
        // Add opening hours
        $this->add_opening_hours( $schema );
        
        // Add accepts reservations (for restaurants)
        if ( $schema['@type'] === 'Restaurant' ) {
            $accepts_reservations = \get_option( 'khm_seo_accepts_reservations' );
            if ( ! empty( $accepts_reservations ) ) {
                $schema['acceptsReservations'] = $accepts_reservations === 'yes';
            }
        }
        
        // Add geo coordinates if available
        $this->add_geo_coordinates( $schema );
    }
    
    /**
     * Add opening hours to schema
     * 
     * @param array $schema Schema array (by reference)
     */
    private function add_opening_hours( &$schema ) {
        $opening_hours = \get_option( 'khm_seo_opening_hours', array() );
        
        if ( empty( $opening_hours ) ) {
            return;
        }
        
        $opening_hours_spec = array();
        $days_of_week = array(
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
        );
        
        foreach ( $days_of_week as $day_key => $day_name ) {
            if ( ! empty( $opening_hours[ $day_key ] ) ) {
                $day_hours = $opening_hours[ $day_key ];
                
                if ( ! empty( $day_hours['open'] ) && ! empty( $day_hours['close'] ) ) {
                    $opening_hours_spec[] = array(
                        '@type' => 'OpeningHoursSpecification',
                        'dayOfWeek' => $day_name,
                        'opens' => $day_hours['open'],
                        'closes' => $day_hours['close'],
                    );
                }
            }
        }
        
        if ( ! empty( $opening_hours_spec ) ) {
            $schema['openingHoursSpecification'] = $opening_hours_spec;
        }
    }
    
    /**
     * Add geo coordinates to schema
     * 
     * @param array $schema Schema array (by reference)
     */
    private function add_geo_coordinates( &$schema ) {
        $latitude = \get_option( 'khm_seo_business_latitude' );
        $longitude = \get_option( 'khm_seo_business_longitude' );
        
        if ( ! empty( $latitude ) && ! empty( $longitude ) ) {
            $schema['geo'] = array(
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
            );
        }
    }
    
    /**
     * Get configuration value
     * 
     * @param string $key Configuration key
     * @param mixed  $default Default value
     * @return mixed Configuration value
     */
    public function get_config( $key, $default = null ) {
        return isset( $this->config[ $key ] ) ? $this->config[ $key ] : $default;
    }
    
    /**
     * Update configuration
     * 
     * @param array $new_config New configuration values
     */
    public function update_config( $new_config ) {
        $this->config = array_merge( $this->config, $new_config );
    }
}
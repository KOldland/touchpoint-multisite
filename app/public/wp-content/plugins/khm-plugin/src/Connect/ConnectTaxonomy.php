<?php

namespace KHM\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical taxonomy: site slugs → expertise areas and industry verticals.
 *
 * This is the single source of truth for what each Connect network site covers.
 * Mirrors ConnectSubscriptionEndpoint::ALL_SITES for labels; add industry mappings here.
 */
class ConnectTaxonomy {

	/**
	 * Site slug → expertise label.
	 * Must stay in sync with ConnectSubscriptionEndpoint::ALL_SITES.
	 */
	const EXPERTISE_AREAS = [
		'pricing'                => 'Revenue Operations',
		'aftermarket'            => 'Aftermarket Operations',
		'field-service'          => 'Field Service Management',
		'spare-parts'            => 'Spare Parts & Logistics',
		'ecommerce'              => 'Industrial eCommerce',
		'industrial'             => 'Industrial Operations',
		'aerospace'              => 'Aerospace Engineering',
		'utilities-ops'          => 'Utilities Operations',
		'built-env'              => 'Infrastructure Operations',
		'manufacturing-flagship' => 'Modern Manufacturing',
	];

	/**
	 * Site slug → industry vertical slugs applicable to that site.
	 */
	const SITE_INDUSTRIES = [
		'pricing'                => [ 'manufacturing', 'industrial' ],
		'aftermarket'            => [ 'manufacturing', 'industrial' ],
		'field-service'          => [ 'manufacturing', 'industrial', 'utilities' ],
		'spare-parts'            => [ 'manufacturing', 'industrial' ],
		'ecommerce'              => [ 'manufacturing', 'industrial' ],
		'industrial'             => [ 'industrial', 'manufacturing' ],
		'aerospace'              => [ 'aerospace' ],
		'utilities-ops'          => [ 'utilities', 'energy' ],
		'built-env'              => [ 'construction', 'infrastructure' ],
		'manufacturing-flagship' => [ 'manufacturing' ],
	];

	/**
	 * Industry slug → human-readable label.
	 */
	const INDUSTRY_LABELS = [
		'manufacturing'  => 'Manufacturing',
		'industrial'     => 'Industrial Engineering',
		'aerospace'      => 'Aviation & Aerospace',
		'utilities'      => 'Utilities',
		'energy'         => 'Energy',
		'construction'   => 'Construction',
		'infrastructure' => 'Infrastructure',
	];

	/**
	 * Focus area slug → content channels.
	 *
	 * Each channel is keyed by its slug and contains:
	 *   label    - display name
	 *   problems - array of buyer challenges, each with slug, label, and its own solutions map
	 *              (solutions: software / hardware / consultancy — empty arrays hidden in UI)
	 */
	const FOCUS_AREA_CHANNELS = [
		'pricing' => [
			'demand-ops' => [
				'label'    => 'Demand Operations',
				'problems' => [
					[ 'slug' => 'lead-routing',  'label' => 'Improve lead routing efficiency', 'solutions' => [
						'software'    => [ 'CRM', 'Lead Routing Software', 'ABM Platforms', 'Marketing Automation (MAP)' ],
						'hardware'    => [],
						'consultancy' => [ 'RevOps Strategy', 'Revenue Process Optimisation', 'MarTech Stack Audits' ],
					]],
					[ 'slug' => 'pipeline-vis',  'label' => 'Improve pipeline visibility', 'solutions' => [
						'software'    => [ 'CRM', 'Revenue Intelligence Platforms', 'Forecasting Tools' ],
						'hardware'    => [],
						'consultancy' => [ 'RevOps Strategy', 'Pipeline Audits' ],
					]],
					[ 'slug' => 'sales-mktg',    'label' => 'Align sales and marketing teams', 'solutions' => [
						'software'    => [ 'CRM', 'Marketing Automation (MAP)', 'ABM Platforms' ],
						'hardware'    => [],
						'consultancy' => [ 'RevOps Strategy', 'Demand Generation Consulting' ],
					]],
					[ 'slug' => 'conversion',    'label' => 'Increase lead conversion rates', 'solutions' => [
						'software'    => [ 'CRM', 'Marketing Automation (MAP)', 'ABM Platforms', 'Intent Data Providers', 'Conversational Intelligence Platforms' ],
						'hardware'    => [],
						'consultancy' => [ 'Demand Generation Consulting', 'Sales Coaching', 'MarTech Stack Audits' ],
					]],
					[ 'slug' => 'data-entry',    'label' => 'Automate manual data entry and CRM updates', 'solutions' => [
						'software'    => [ 'CRM', 'Marketing Automation (MAP)', 'Revenue Intelligence Platforms', 'Data Hygiene Solutions' ],
						'hardware'    => [],
						'consultancy' => [ 'RevOps Strategy', 'Change Management', 'MarTech Stack Audits' ],
					]],
					[ 'slug' => 'intent-data',   'label' => 'Leverage intent data for buyer targeting', 'solutions' => [
						'software'    => [ 'ABM Platforms', 'Intent Data Providers', 'Marketing Automation (MAP)' ],
						'hardware'    => [],
						'consultancy' => [ 'Demand Generation Consulting' ],
					]],
					[ 'slug' => 'tech-stack',    'label' => 'Integrate and streamline the tech stack', 'solutions' => [
						'software'    => [ 'CRM', 'Revenue Intelligence Platforms', 'ABM Platforms', 'Marketing Automation (MAP)', 'Intent Data Providers', 'Lead Routing Software' ],
						'hardware'    => [],
						'consultancy' => [ 'MarTech Stack Audits', 'RevOps Strategy' ],
					]],
				],
			],
			'price-margin' => [
				'label'    => 'Price & Margin Excellence',
				'problems' => [
					[ 'slug' => 'margin-leakage',      'label' => 'Reduce margin leakage', 'solutions' => [
						'software'    => [ 'CPQ (Configure, Price, Quote)', 'Pricing Optimisation Engines', 'Margin Analysis Tools' ],
						'hardware'    => [],
						'consultancy' => [ 'Pricing Strategy Consulting', 'Deal Desk Optimisation', 'Value-Based Pricing Workshops' ],
					]],
					[ 'slug' => 'pricing-consistency', 'label' => 'Improve pricing consistency', 'solutions' => [
						'software'    => [ 'CPQ (Configure, Price, Quote)', 'Pricing Optimisation Engines' ],
						'hardware'    => [],
						'consultancy' => [ 'Pricing Strategy Consulting', 'Value-Based Pricing Workshops' ],
					]],
					[ 'slug' => 'discounting',         'label' => 'Strengthen discounting discipline', 'solutions' => [
						'software'    => [ 'CPQ (Configure, Price, Quote)', 'Pricing Optimisation Engines', 'Margin Analysis Tools' ],
						'hardware'    => [],
						'consultancy' => [ 'Pricing Strategy Consulting', 'Deal Desk Optimisation' ],
					]],
					[ 'slug' => 'cogs-inflation',      'label' => 'Manage inflation impact on COGS', 'solutions' => [
						'software'    => [ 'Pricing Optimisation Engines', 'Margin Analysis Tools' ],
						'hardware'    => [],
						'consultancy' => [ 'Pricing Strategy Consulting', 'Value-Based Pricing Workshops' ],
					]],
					[ 'slug' => 'value-pricing',       'label' => 'Adopt value-based pricing', 'solutions' => [
						'software'    => [ 'CPQ (Configure, Price, Quote)', 'Pricing Optimisation Engines' ],
						'hardware'    => [],
						'consultancy' => [ 'Value-Based Pricing Workshops', 'Pricing Strategy Consulting' ],
					]],
					[ 'slug' => 'deal-desk',           'label' => 'Automate deal desk operations', 'solutions' => [
						'software'    => [ 'CPQ (Configure, Price, Quote)', 'Margin Analysis Tools', 'CRM', 'Revenue Planning Tools' ],
						'hardware'    => [],
						'consultancy' => [ 'Deal Desk Optimisation', 'RevOps Strategy', 'Pricing Strategy Consulting' ],
					]],
				],
			],
			'rebate-incentive' => [
				'label'    => 'Rebate & Incentive Management',
				'problems' => [
					[ 'slug' => 'channel-incentives', 'label' => 'Improve channel incentive effectiveness', 'solutions' => [
						'software'    => [ 'Rebate Management Software', 'Partner Relationship Management (PRM)', 'Incentive Compensation Management (ICM)' ],
						'hardware'    => [],
						'consultancy' => [ 'Incentive Programme Design', 'Channel Compliance Audits' ],
					]],
					[ 'slug' => 'rebate-calc',        'label' => 'Simplify rebate calculations', 'solutions' => [
						'software'    => [ 'Rebate Management Software', 'Incentive Compensation Management (ICM)' ],
						'hardware'    => [],
						'consultancy' => [ 'Incentive Programme Design', 'RevOps Strategy' ],
					]],
					[ 'slug' => 'dispute-resolution', 'label' => 'Streamline rebate dispute resolution', 'solutions' => [
						'software'    => [ 'Rebate Management Software', 'Partner Relationship Management (PRM)', 'CRM' ],
						'hardware'    => [],
						'consultancy' => [ 'Incentive Programme Design', 'Channel Compliance Audits', 'Change Management' ],
					]],
					[ 'slug' => 'mdf-roi',            'label' => 'Improve MDF ROI tracking', 'solutions' => [
						'software'    => [ 'Partner Relationship Management (PRM)', 'Marketing Automation (MAP)', 'CRM', 'Rebate Management Software', 'Incentive Compensation Management (ICM)' ],
						'hardware'    => [],
						'consultancy' => [ 'Incentive Programme Design', 'Channel Compliance Audits', 'RevOps Strategy' ],
					]],
					[ 'slug' => 'rebate-overpayment', 'label' => 'Eliminate rebate overpayments', 'solutions' => [
						'software'    => [ 'Rebate Management Software', 'Incentive Compensation Management (ICM)' ],
						'hardware'    => [],
						'consultancy' => [ 'Channel Compliance Audits' ],
					]],
				],
			],
			'commercial-strategy' => [
				'label'    => 'Commercial Strategy',
				'problems' => [
					[ 'slug' => 'gtm-strategy',      'label' => 'Clarify go-to-market strategy', 'solutions' => [
						'software'    => [ 'Revenue Planning Tools', 'Sales Performance Management (SPM)', 'Territory Planning Software' ],
						'hardware'    => [],
						'consultancy' => [ 'GTM Strategy Consulting', 'Sales Methodology Training', 'Change Management' ],
					]],
					[ 'slug' => 'territory-design',  'label' => 'Improve territory design', 'solutions' => [
						'software'    => [ 'Territory Planning Software', 'Sales Performance Management (SPM)' ],
						'hardware'    => [],
						'consultancy' => [ 'GTM Strategy Consulting' ],
					]],
					[ 'slug' => 'revenue-goals',     'label' => 'Align revenue goals across teams', 'solutions' => [
						'software'    => [ 'Revenue Planning Tools', 'Sales Performance Management (SPM)' ],
						'hardware'    => [],
						'consultancy' => [ 'GTM Strategy Consulting', 'Change Management' ],
					]],
					[ 'slug' => 'churn',             'label' => 'Reduce customer churn', 'solutions' => [
						'software'    => [ 'Revenue Planning Tools', 'Revenue Intelligence Platforms', 'CRM', 'Customer Success Platforms' ],
						'hardware'    => [],
						'consultancy' => [ 'GTM Strategy Consulting', 'Sales Coaching', 'Sales Methodology Training' ],
					]],
					[ 'slug' => 'sales-methodology', 'label' => 'Improve sales methodology effectiveness', 'solutions' => [
						'software'    => [ 'Conversational Intelligence Platforms', 'Revenue Intelligence Platforms' ],
						'hardware'    => [],
						'consultancy' => [ 'Sales Methodology Training', 'Sales Coaching', 'GTM Strategy Consulting', 'Change Management' ],
					]],
				],
			],
			'revenue-intelligence' => [
				'label'    => 'Revenue Intelligence',
				'problems' => [
					[ 'slug' => 'forecast-accuracy', 'label' => 'Improve forecast accuracy', 'solutions' => [
						'software'    => [ 'Revenue Intelligence Platforms', 'Forecasting Tools', 'Data Hygiene Solutions' ],
						'hardware'    => [],
						'consultancy' => [ 'Pipeline Audits', 'Revenue Process Optimisation' ],
					]],
					[ 'slug' => 'pipeline-quality',  'label' => 'Improve pipeline quality', 'solutions' => [
						'software'    => [ 'Revenue Intelligence Platforms', 'Intent Data Providers', 'Conversational Intelligence Platforms', 'Data Hygiene Solutions' ],
						'hardware'    => [],
						'consultancy' => [ 'Pipeline Audits', 'Demand Generation Consulting', 'Sales Coaching' ],
					]],
					[ 'slug' => 'deal-visibility',   'label' => 'Increase deal visibility', 'solutions' => [
						'software'    => [ 'Revenue Intelligence Platforms', 'Conversational Intelligence Platforms', 'Forecasting Tools' ],
						'hardware'    => [],
						'consultancy' => [ 'Pipeline Audits', 'Revenue Process Optimisation' ],
					]],
					[ 'slug' => 'crm-automation',    'label' => 'Automate CRM data capture', 'solutions' => [
						'software'    => [ 'Conversational Intelligence Platforms', 'Revenue Intelligence Platforms', 'Data Hygiene Solutions', 'CRM' ],
						'hardware'    => [],
						'consultancy' => [ 'Revenue Process Optimisation' ],
					]],
					[ 'slug' => 'revenue-targets',   'label' => 'Consistently hit revenue targets', 'solutions' => [
						'software'    => [ 'Revenue Planning Tools', 'Revenue Intelligence Platforms', 'Forecasting Tools', 'Sales Performance Management (SPM)' ],
						'hardware'    => [],
						'consultancy' => [ 'GTM Strategy Consulting', 'Sales Coaching', 'Revenue Process Optimisation' ],
					]],
					[ 'slug' => 'churn-risk',        'label' => 'Proactively identify and reduce churn risk', 'solutions' => [
						'software'    => [ 'Revenue Intelligence Platforms', 'Conversational Intelligence Platforms', 'CRM', 'Customer Success Platforms' ],
						'hardware'    => [],
						'consultancy' => [ 'GTM Strategy Consulting', 'Sales Coaching', 'Pipeline Audits' ],
					]],
				],
			],
		],
		'aftermarket' => [
			'installed-base' => [
				'label'    => 'Installed Base & Lifecycle',
				'problems' => [
					[ 'slug' => 'unplanned-downtime', 'label' => 'Reduce unplanned downtime', 'solutions' => [
						'software'    => [ 'EAM (Enterprise Asset Management)', 'CMMS', 'Asset Performance Management (APM)', 'Predictive Maintenance (PdM) Software', 'Digital Twin Platforms', 'IoT Data Monetisation Platforms', 'Field Service Management (FSM)' ],
						'hardware'    => [ 'IoT Sensors', 'Telematics Devices', 'Edge Computing Devices' ],
						'consultancy' => [ 'Asset Lifecycle Consulting', 'Reliability-Centred Maintenance (RCM) Consulting', 'IT/OT Convergence Consulting', 'Digital Transformation Strategy' ],
					]],
					[ 'slug' => 'asset-visibility',   'label' => 'Improve installed base visibility', 'solutions' => [
						'software'    => [ 'EAM (Enterprise Asset Management)', 'CMMS', 'Asset Performance Management (APM)', 'Digital Twin Platforms', 'IoT Data Monetisation Platforms', 'Field Service Management (FSM)' ],
						'hardware'    => [ 'IoT Sensors', 'Telematics Devices', 'Edge Computing Devices' ],
						'consultancy' => [ 'Asset Lifecycle Consulting', 'IT/OT Convergence Consulting', 'Digital Transformation Strategy' ],
					]],
					[ 'slug' => 'parts-forecasting',  'label' => 'Improve spare parts forecast accuracy', 'solutions' => [
						'software'    => [ 'EAM (Enterprise Asset Management)', 'CMMS', 'Asset Performance Management (APM)', 'Service Parts Inventory Management', 'Supply Chain Planning', 'Demand Forecasting Tools' ],
						'hardware'    => [ 'IoT Sensors', 'Telematics Devices' ],
						'consultancy' => [ 'Asset Lifecycle Consulting', 'Service P&L Management', 'S&OP Consulting' ],
					]],
					[ 'slug' => 'obsolescence',       'label' => 'Manage parts obsolescence risk', 'solutions' => [
						'software'    => [ 'EAM (Enterprise Asset Management)', 'CMMS', 'Service Parts Inventory Management', 'Inventory Optimisation' ],
						'hardware'    => [ 'IoT Sensors' ],
						'consultancy' => [ 'Obsolescence Management', 'Asset Lifecycle Consulting' ],
					]],
					[ 'slug' => 'service-revenue',    'label' => 'Capture missed service revenue opportunities', 'solutions' => [
						'software'    => [ 'EAM (Enterprise Asset Management)', 'CMMS', 'Asset Performance Management (APM)', 'Service Lifecycle Management (SLM)', 'Subscription Billing Engines', 'IoT Data Monetisation Platforms', 'Field Service Management (FSM)', 'Customer Success Platforms' ],
						'hardware'    => [ 'IoT Sensors', 'Telematics Devices' ],
						'consultancy' => [ 'Asset Lifecycle Consulting', 'Servitization Strategy', 'Aftermarket Sales Enablement', 'Pricing Strategy for XaaS' ],
					]],
				],
			],
			'service-models' => [
				'label'    => 'Service Business Models',
				'problems' => [
					[ 'slug' => 'product-revenue',   'label' => 'Grow revenue beyond product sales', 'solutions' => [
						'software'    => [ 'Subscription Billing Engines', 'Service Lifecycle Management (SLM)', 'IoT Data Monetisation Platforms', 'B2B eCommerce Platforms' ],
						'hardware'    => [],
						'consultancy' => [ 'Servitization Strategy', 'Pricing Strategy for XaaS', 'Change Management for Services' ],
					]],
					[ 'slug' => 'data-monetisation', 'label' => 'Monetise service and asset data', 'solutions' => [
						'software'    => [ 'IoT Data Monetisation Platforms', 'Service Lifecycle Management (SLM)', 'Digital Twin Platforms', 'Subscription Billing Engines', 'Asset Performance Management (APM)' ],
						'hardware'    => [ 'IoT Sensors', 'Telematics Devices', 'Edge Computing Devices' ],
						'consultancy' => [ 'Servitization Strategy', 'Pricing Strategy for XaaS', 'Digital Transformation Strategy', 'IT/OT Convergence Consulting' ],
					]],
					[ 'slug' => 'as-a-service',      'label' => 'Simplify the transition to as-a-service models', 'solutions' => [
						'software'    => [ 'Subscription Billing Engines', 'Service Lifecycle Management (SLM)', 'IoT Data Monetisation Platforms', 'Customer Success Platforms' ],
						'hardware'    => [ 'IoT Sensors', 'Edge Computing Devices' ],
						'consultancy' => [ 'Servitization Strategy', 'Change Management for Services', 'Pricing Strategy for XaaS' ],
					]],
					[ 'slug' => 'recurring-revenue', 'label' => 'Build predictable recurring revenue streams', 'solutions' => [
						'software'    => [ 'Subscription Billing Engines', 'Service Lifecycle Management (SLM)', 'IoT Data Monetisation Platforms', 'Customer Success Platforms' ],
						'hardware'    => [],
						'consultancy' => [ 'Servitization Strategy', 'Pricing Strategy for XaaS', 'Change Management for Services' ],
					]],
				],
			],
			'aftermarket-profit' => [
				'label'    => 'Aftermarket Profitability',
				'problems' => [
					[ 'slug' => 'aftermarket-margins', 'label' => 'Improve aftermarket margins', 'solutions' => [
						'software'    => [ 'Pricing Optimisation Software', 'Service Parts Inventory Management', 'Service Lifecycle Management (SLM)' ],
						'hardware'    => [],
						'consultancy' => [ 'Service P&L Management', 'Aftermarket Sales Enablement', 'Pricing Strategy for XaaS' ],
					]],
					[ 'slug' => 'grey-market',         'label' => 'Defend against grey market competition', 'solutions' => [
						'software'    => [ 'Pricing Optimisation Software', 'Service Parts Inventory Management', 'B2B eCommerce Platforms', 'Service Lifecycle Management (SLM)' ],
						'hardware'    => [],
						'consultancy' => [ 'Service P&L Management', 'Aftermarket Sales Enablement', 'Pricing Strategy for XaaS' ],
					]],
					[ 'slug' => 'am-retention',        'label' => 'Improve aftermarket customer retention', 'solutions' => [
						'software'    => [ 'Customer Success Platforms', 'VoC / Survey Tools', 'Helpdesk Software', 'Field Service Management (FSM)', 'Pricing Optimisation Software', 'Service Parts Inventory Management' ],
						'hardware'    => [],
						'consultancy' => [ 'Churn Prediction Consulting', 'Aftermarket Sales Enablement', 'NPS Optimisation', 'CX Strategy' ],
					]],
					[ 'slug' => 'parts-pricing',       'label' => 'Optimise spare parts pricing', 'solutions' => [
						'software'    => [ 'Pricing Optimisation Software', 'Service Parts Inventory Management' ],
						'hardware'    => [],
						'consultancy' => [ 'Service P&L Management', 'Pricing Strategy for XaaS', 'Aftermarket Sales Enablement' ],
					]],
				],
			],
			'digital-transform' => [
				'label'    => 'Digital Transformation',
				'problems' => [
					[ 'slug' => 'disconnected-systems', 'label' => 'Connect and integrate service systems', 'solutions' => [
						'software'    => [ 'Field Service Management (FSM)', 'AR/VR Remote Assistance', 'Digital Twin Platforms' ],
						'hardware'    => [ 'AR/VR Headsets', 'Edge Computing Devices' ],
						'consultancy' => [ 'Digital Transformation Strategy', 'IT/OT Convergence Consulting' ],
					]],
					[ 'slug' => 'realtime-visibility',  'label' => 'Achieve real-time asset visibility', 'solutions' => [
						'software'    => [ 'Field Service Management (FSM)', 'Digital Twin Platforms', 'Asset Performance Management (APM)', 'EAM (Enterprise Asset Management)' ],
						'hardware'    => [ 'IoT Sensors', 'Telematics Devices', 'Edge Computing Devices' ],
						'consultancy' => [ 'Digital Transformation Strategy', 'IT/OT Convergence Consulting' ],
					]],
					[ 'slug' => 'work-orders',          'label' => 'Automate work order management', 'solutions' => [
						'software'    => [ 'Field Service Management (FSM)', 'AR/VR Remote Assistance', 'CMMS', 'EAM (Enterprise Asset Management)' ],
						'hardware'    => [ 'AR/VR Headsets', 'Edge Computing Devices' ],
						'consultancy' => [ 'Digital Transformation Strategy', 'Service Process Optimisation' ],
					]],
					[ 'slug' => 'legacy-systems',       'label' => 'Modernise legacy service systems', 'solutions' => [
						'software'    => [ 'Field Service Management (FSM)', 'Digital Twin Platforms', 'AR/VR Remote Assistance' ],
						'hardware'    => [ 'AR/VR Headsets', 'Edge Computing Devices' ],
						'consultancy' => [ 'Digital Transformation Strategy', 'IT/OT Convergence Consulting' ],
					]],
					[ 'slug' => 'parts-traceability',   'label' => 'Improve parts traceability', 'solutions' => [
						'software'    => [ 'Field Service Management (FSM)', 'Service Parts Inventory Management', 'EAM (Enterprise Asset Management)', 'CMMS', 'Digital Twin Platforms' ],
						'hardware'    => [ 'IoT Sensors', 'Telematics Devices', 'Edge Computing Devices' ],
						'consultancy' => [ 'Asset Lifecycle Consulting', 'Service Process Optimisation', 'Digital Transformation Strategy', 'IT/OT Convergence Consulting' ],
					]],
				],
			],
			'customer-loyalty' => [
				'label'    => 'Customer Loyalty & Brand',
				'problems' => [
					[ 'slug' => 'cust-churn',        'label' => 'Reduce customer churn and improve retention', 'solutions' => [
						'software'    => [ 'Customer Success Platforms', 'VoC / Survey Tools', 'Helpdesk Software' ],
						'hardware'    => [],
						'consultancy' => [ 'Churn Prediction Consulting', 'Customer Journey Mapping', 'CX Strategy', 'NPS Optimisation' ],
					]],
					[ 'slug' => 'cust-satisfaction', 'label' => 'Improve customer satisfaction scores', 'solutions' => [
						'software'    => [ 'Customer Success Platforms', 'VoC / Survey Tools', 'Helpdesk Software', 'Field Service Management (FSM)', 'AR/VR Remote Assistance' ],
						'hardware'    => [ 'AR/VR Headsets' ],
						'consultancy' => [ 'CX Strategy', 'NPS Optimisation', 'Customer Journey Mapping' ],
					]],
					[ 'slug' => 'service-journeys',  'label' => 'Create seamless end-to-end service journeys', 'solutions' => [
						'software'    => [ 'Customer Success Platforms', 'Service Lifecycle Management (SLM)', 'Helpdesk Software', 'Field Service Management (FSM)' ],
						'hardware'    => [],
						'consultancy' => [ 'Customer Journey Mapping', 'CX Strategy', 'Digital Transformation Strategy' ],
					]],
					[ 'slug' => 'proactive-comms',   'label' => 'Improve proactive customer communication', 'solutions' => [
						'software'    => [ 'Customer Success Platforms', 'VoC / Survey Tools', 'Helpdesk Software', 'Field Service Management (FSM)', 'Asset Performance Management (APM)' ],
						'hardware'    => [ 'IoT Sensors', 'Telematics Devices' ],
						'consultancy' => [ 'CX Strategy', 'NPS Optimisation', 'Change Management' ],
					]],
					[ 'slug' => 'self-service',      'label' => 'Increase self-service portal adoption', 'solutions' => [
						'software'    => [ 'Customer Success Platforms', 'Helpdesk Software', 'B2B eCommerce Platforms', 'Customer Portals' ],
						'hardware'    => [],
						'consultancy' => [ 'Customer Journey Mapping', 'CX Strategy', 'Change Management' ],
					]],
				],
			],
		],
		'field-service' => [
			'maintenance' => [
				'label'    => 'Maintenance Strategy',
				'problems' => [
					[ 'slug' => 'corrective-costs',   'label' => 'Reduce corrective maintenance costs', 'solutions' => [
						'software'    => [ 'CMMS', 'EAM', 'Field Service Management (FSM)', 'Predictive Maintenance (PdM) Software', 'BI Tools' ],
						'hardware'    => [ 'Vibration Sensors', 'Thermal Cameras', 'NDT Equipment' ],
						'consultancy' => [ 'Reliability-Centred Maintenance (RCM) Consulting', 'FMEA Workshops', 'Profitability Analysis', 'Dispatch Optimisation' ],
					]],
					[ 'slug' => 'equipment-failures', 'label' => 'Reduce unplanned equipment failures', 'solutions' => [
						'software'    => [ 'CMMS', 'EAM', 'Predictive Maintenance (PdM) Software' ],
						'hardware'    => [ 'Vibration Sensors', 'Thermal Cameras', 'NDT Equipment' ],
						'consultancy' => [ 'Reliability-Centred Maintenance (RCM) Consulting', 'FMEA Workshops' ],
					]],
					[ 'slug' => 'first-time-fix',     'label' => 'Improve first-time fix rates', 'solutions' => [
						'software'    => [ 'CMMS', 'EAM', 'Field Service Management (FSM)', 'Mobile Workforce Apps', 'Remote Diagnostics Tools', 'AR Training Platforms', 'Field Activity Capture' ],
						'hardware'    => [ 'AR Glasses', 'Rugged Tablets & Mobiles' ],
						'consultancy' => [ 'Reliability-Centred Maintenance (RCM) Consulting', 'Field Service Training', 'Service Quality Assurance' ],
					]],
					[ 'slug' => 'maint-scheduling',   'label' => 'Optimise maintenance scheduling', 'solutions' => [
						'software'    => [ 'CMMS', 'EAM', 'Field Service Management (FSM)', 'Field Service Routing Software', 'Dynamic Scheduling Software' ],
						'hardware'    => [],
						'consultancy' => [ 'Reliability-Centred Maintenance (RCM) Consulting', 'Dispatch Optimisation', 'KPI Framework Design' ],
					]],
					[ 'slug' => 'predictive-maint',   'label' => 'Implement predictive maintenance', 'solutions' => [
						'software'    => [ 'Predictive Maintenance (PdM) Software', 'CMMS', 'EAM', 'Digital Twin Platforms', 'BI Tools', 'Executive Dashboards' ],
						'hardware'    => [ 'Vibration Sensors', 'Thermal Cameras', 'NDT Equipment' ],
						'consultancy' => [ 'Reliability-Centred Maintenance (RCM) Consulting', 'FMEA Workshops' ],
					]],
				],
			],
			'scheduling-dispatch' => [
				'label'    => 'Scheduling, Dispatch & Logistics',
				'problems' => [
					[ 'slug' => 'tech-routing',        'label' => 'Optimise technician routing', 'solutions' => [
						'software'    => [ 'Field Service Routing Software', 'Fleet Management Systems', 'RTLS', 'Field Service Management (FSM)', 'Dynamic Scheduling Software' ],
						'hardware'    => [ 'GPS Trackers', 'Telematics Hardware' ],
						'consultancy' => [ 'Dispatch Optimisation', 'Fleet Efficiency Consulting' ],
					]],
					[ 'slug' => 'sla-adherence',       'label' => 'Improve SLA adherence', 'solutions' => [
						'software'    => [ 'Field Service Routing Software', 'Field Service Management (FSM)', 'Customer Portals', 'Executive Dashboards', 'Dynamic Scheduling Software', 'Omnichannel Support Platforms' ],
						'hardware'    => [ 'GPS Trackers', 'Rugged Tablets & Mobiles' ],
						'consultancy' => [ 'Dispatch Optimisation', 'SLA Management Consulting', 'KPI Framework Design' ],
					]],
					[ 'slug' => 'parts-inventory-vis', 'label' => 'Improve parts inventory visibility', 'solutions' => [
						'software'    => [ 'RTLS', 'Field Service Routing Software', 'Field Service Management (FSM)', 'EAM', 'CMMS', 'Field Activity Capture', 'Service Parts Inventory Management', 'Inventory Management (IM)', 'Inventory Optimisation' ],
						'hardware'    => [ 'GPS Trackers', 'Rugged Tablets & Mobiles', 'Wearables' ],
						'consultancy' => [ 'Dispatch Optimisation', 'Data Consolidation Consulting', 'KPI Framework Design' ],
					]],
					[ 'slug' => 'dispatch-costs',      'label' => 'Reduce dispatch costs', 'solutions' => [
						'software'    => [ 'Field Service Routing Software', 'Fleet Management Systems', 'Field Service Management (FSM)', 'Dynamic Scheduling Software', 'Remote Diagnostics Tools' ],
						'hardware'    => [ 'GPS Trackers', 'Telematics Hardware' ],
						'consultancy' => [ 'Dispatch Optimisation', 'Fleet Efficiency Consulting' ],
					]],
					[ 'slug' => 'realtime-tracking',   'label' => 'Enable real-time technician tracking', 'solutions' => [
						'software'    => [ 'Field Service Routing Software', 'RTLS', 'Field Service Management (FSM)', 'Fleet Management Systems', 'Mobile Workforce Apps' ],
						'hardware'    => [ 'GPS Trackers', 'Telematics Hardware', 'Wearables' ],
						'consultancy' => [ 'Dispatch Optimisation', 'Fleet Efficiency Consulting' ],
					]],
				],
			],
			'workforce-talent' => [
				'label'    => 'Workforce & Talent',
				'problems' => [
					[ 'slug' => 'aging-workforce',   'label' => 'Manage aging workforce and knowledge transfer', 'solutions' => [
						'software'    => [ 'LMS (Learning Management Systems)', 'Mobile Workforce Apps', 'AR Training Platforms' ],
						'hardware'    => [ 'AR Glasses', 'Rugged Tablets & Mobiles', 'Wearables' ],
						'consultancy' => [ 'Skills Gap Analysis', 'Talent Acquisition Strategy', 'Field Service Training' ],
					]],
					[ 'slug' => 'skills-gap',        'label' => 'Close the technician skills gap', 'solutions' => [
						'software'    => [ 'LMS (Learning Management Systems)', 'Mobile Workforce Apps', 'AR Training Platforms' ],
						'hardware'    => [ 'AR Glasses', 'Rugged Tablets & Mobiles', 'Wearables' ],
						'consultancy' => [ 'Skills Gap Analysis', 'Field Service Training' ],
					]],
					[ 'slug' => 'tech-turnover',     'label' => 'Reduce technician turnover', 'solutions' => [
						'software'    => [ 'LMS (Learning Management Systems)', 'Mobile Workforce Apps', 'AR Training Platforms' ],
						'hardware'    => [ 'AR Glasses', 'Wearables' ],
						'consultancy' => [ 'Skills Gap Analysis', 'Talent Acquisition Strategy', 'Field Service Training' ],
					]],
					[ 'slug' => 'tech-training',     'label' => 'Improve technician training programmes', 'solutions' => [
						'software'    => [ 'LMS (Learning Management Systems)', 'Mobile Workforce Apps', 'AR Training Platforms' ],
						'hardware'    => [ 'AR Glasses', 'Wearables' ],
						'consultancy' => [ 'Field Service Training', 'Talent Acquisition Strategy' ],
					]],
					[ 'slug' => 'safety-compliance', 'label' => 'Strengthen safety compliance', 'solutions' => [
						'software'    => [ 'LMS (Learning Management Systems)', 'Mobile Workforce Apps', 'Field Activity Capture' ],
						'hardware'    => [ 'Wearables', 'Rugged Tablets & Mobiles' ],
						'consultancy' => [ 'Skills Gap Analysis', 'Field Service Training', 'Service Quality Assurance', 'KPI Framework Design' ],
					]],
				],
			],
			'service-cx' => [
				'label'    => 'Service Delivery & CX',
				'problems' => [
					[ 'slug' => 'cust-resolution', 'label' => 'Improve customer-facing first-time resolution', 'solutions' => [
						'software'    => [ 'Omnichannel Support Platforms', 'Customer Portals', 'Remote Diagnostics Tools', 'Field Service Management (FSM)' ],
						'hardware'    => [],
						'consultancy' => [ 'SLA Management Consulting', 'Service Quality Assurance' ],
					]],
					[ 'slug' => 'cust-comms',      'label' => 'Improve customer communication throughout service', 'solutions' => [
						'software'    => [ 'Omnichannel Support Platforms', 'Customer Portals', 'Field Service Management (FSM)' ],
						'hardware'    => [],
						'consultancy' => [ 'SLA Management Consulting', 'Service Quality Assurance', 'CX Strategy' ],
					]],
					[ 'slug' => 'sla-performance', 'label' => 'Consistently meet service SLAs', 'solutions' => [
						'software'    => [ 'Omnichannel Support Platforms', 'Customer Portals', 'Field Service Management (FSM)', 'Field Service Routing Software' ],
						'hardware'    => [],
						'consultancy' => [ 'SLA Management Consulting', 'Service Quality Assurance' ],
					]],
					[ 'slug' => 'customer-effort', 'label' => 'Reduce customer effort', 'solutions' => [
						'software'    => [ 'Omnichannel Support Platforms', 'Customer Portals', 'Remote Diagnostics Tools' ],
						'hardware'    => [],
						'consultancy' => [ 'Customer Journey Mapping', 'SLA Management Consulting', 'Service Quality Assurance', 'KPI Framework Design' ],
					]],
					[ 'slug' => 'escalation-mgmt', 'label' => 'Streamline escalation management', 'solutions' => [
						'software'    => [ 'Omnichannel Support Platforms', 'Customer Portals', 'Remote Diagnostics Tools', 'Executive Dashboards', 'BI Tools' ],
						'hardware'    => [],
						'consultancy' => [ 'SLA Management Consulting', 'Service Quality Assurance', 'KPI Framework Design' ],
					]],
				],
			],
			'ops-visibility' => [
				'label'    => 'Operational Visibility & KPIs',
				'problems' => [
					[ 'slug' => 'mttr',                'label' => 'Reduce mean time to repair (MTTR)', 'solutions' => [
						'software'    => [ 'BI Tools', 'Field Activity Capture', 'CMMS', 'Field Service Management (FSM)', 'Remote Diagnostics Tools', 'AR Training Platforms', 'Mobile Workforce Apps' ],
						'hardware'    => [ 'AR Glasses', 'Rugged Tablets & Mobiles' ],
						'consultancy' => [ 'KPI Framework Design', 'Data Consolidation Consulting', 'Field Service Training', 'Service Quality Assurance' ],
					]],
					[ 'slug' => 'tech-utilisation',    'label' => 'Improve technician utilisation', 'solutions' => [
						'software'    => [ 'BI Tools', 'Field Activity Capture', 'Field Service Management (FSM)', 'Field Service Routing Software', 'Dynamic Scheduling Software', 'Executive Dashboards' ],
						'hardware'    => [ 'GPS Trackers', 'Telematics Hardware' ],
						'consultancy' => [ 'KPI Framework Design', 'Profitability Analysis', 'Dispatch Optimisation' ],
					]],
					[ 'slug' => 'realtime-dashboards', 'label' => 'Build real-time operational dashboards', 'solutions' => [
						'software'    => [ 'BI Tools', 'Executive Dashboards', 'Field Activity Capture' ],
						'hardware'    => [],
						'consultancy' => [ 'KPI Framework Design', 'Data Consolidation Consulting' ],
					]],
					[ 'slug' => 'data-quality',        'label' => 'Improve field service data quality', 'solutions' => [
						'software'    => [ 'BI Tools', 'Field Activity Capture', 'Field Service Management (FSM)' ],
						'hardware'    => [ 'Rugged Tablets & Mobiles' ],
						'consultancy' => [ 'KPI Framework Design', 'Data Consolidation Consulting' ],
					]],
					[ 'slug' => 'capacity-planning',   'label' => 'Improve capacity planning accuracy', 'solutions' => [
						'software'    => [ 'BI Tools', 'Executive Dashboards', 'Field Activity Capture', 'Field Service Management (FSM)', 'Dynamic Scheduling Software' ],
						'hardware'    => [],
						'consultancy' => [ 'KPI Framework Design', 'Profitability Analysis' ],
					]],
				],
			],
			'digital-transform' => [
				'label'    => 'Digital Transformation',
				'problems' => [
					[ 'slug' => 'disconnected-systems', 'label' => 'Connect and integrate service systems', 'solutions' => [
						'software'    => [ 'Field Service Management (FSM)', 'AR/VR Remote Assistance', 'Digital Twin Platforms' ],
						'hardware'    => [ 'AR/VR Headsets', 'Edge Computing Devices' ],
						'consultancy' => [ 'Digital Transformation Strategy', 'IT/OT Convergence Consulting' ],
					]],
					[ 'slug' => 'realtime-visibility',  'label' => 'Achieve real-time asset visibility', 'solutions' => [
						'software'    => [ 'Field Service Management (FSM)', 'Digital Twin Platforms', 'BI Tools' ],
						'hardware'    => [ 'Edge Computing Devices' ],
						'consultancy' => [ 'Digital Transformation Strategy', 'IT/OT Convergence Consulting' ],
					]],
					[ 'slug' => 'work-orders',          'label' => 'Automate work order management', 'solutions' => [
						'software'    => [ 'Field Service Management (FSM)', 'CMMS', 'EAM' ],
						'hardware'    => [ 'Edge Computing Devices' ],
						'consultancy' => [ 'Digital Transformation Strategy' ],
					]],
					[ 'slug' => 'legacy-systems',       'label' => 'Modernise legacy service systems', 'solutions' => [
						'software'    => [ 'Field Service Management (FSM)', 'Digital Twin Platforms', 'AR/VR Remote Assistance' ],
						'hardware'    => [ 'AR/VR Headsets', 'Edge Computing Devices' ],
						'consultancy' => [ 'Digital Transformation Strategy', 'IT/OT Convergence Consulting' ],
					]],
					[ 'slug' => 'parts-traceability',   'label' => 'Improve parts traceability', 'solutions' => [
						'software'    => [ 'Field Service Management (FSM)', 'Digital Twin Platforms', 'RTLS' ],
						'hardware'    => [ 'Edge Computing Devices', 'GPS Trackers' ],
						'consultancy' => [ 'Digital Transformation Strategy', 'IT/OT Convergence Consulting' ],
					]],
				],
			],
		],
		'spare-parts' => [
			'inventory-planning' => [
				'label'    => 'Inventory Planning',
				'problems' => [
					[ 'slug' => 'parts-forecast',     'label' => 'Improve spare parts forecast accuracy', 'solutions' => [
						'software'    => [ 'Supply Chain Planning', 'Inventory Optimisation', 'Inventory Management (IM)', 'Demand Forecasting Tools' ],
						'hardware'    => [],
						'consultancy' => [ 'S&OP Consulting', 'Inventory Policy Design' ],
					]],
					[ 'slug' => 'carrying-costs',     'label' => 'Reduce inventory carrying costs', 'solutions' => [
						'software'    => [ 'Supply Chain Planning', 'Inventory Optimisation' ],
						'hardware'    => [],
						'consultancy' => [ 'Inventory Policy Design', 'S&OP Consulting' ],
					]],
					[ 'slug' => 'stockouts',          'label' => 'Eliminate frequent stockouts', 'solutions' => [
						'software'    => [ 'Supply Chain Planning', 'Inventory Optimisation', 'Inventory Management (IM)', 'Demand Forecasting Tools' ],
						'hardware'    => [],
						'consultancy' => [ 'Inventory Policy Design', 'S&OP Consulting' ],
					]],
					[ 'slug' => 'obsolete-inventory', 'label' => 'Reduce excess and obsolete inventory', 'solutions' => [
						'software'    => [ 'Supply Chain Planning', 'Inventory Optimisation', 'Inventory Management (IM)' ],
						'hardware'    => [],
						'consultancy' => [ 'Inventory Policy Design', 'S&OP Consulting' ],
					]],
					[ 'slug' => 'channel-inv-vis',    'label' => 'Improve channel inventory visibility', 'solutions' => [
						'software'    => [ 'Supply Chain Planning', 'Inventory Optimisation', 'Inventory Management (IM)', 'Demand Forecasting Tools' ],
						'hardware'    => [],
						'consultancy' => [ 'S&OP Consulting', 'Master Data Management (MDM) Consulting' ],
					]],
				],
			],
			'warehouse-ops' => [
				'label'    => 'Warehouse Operations',
				'problems' => [
					[ 'slug' => 'warehouse-layout', 'label' => 'Optimise warehouse layout and flow', 'solutions' => [
						'software'    => [ 'WMS (Warehouse Management Systems)', 'WES (Warehouse Execution Systems)' ],
						'hardware'    => [ 'AS/RS', 'Conveyors', 'Barcode Scanners' ],
						'consultancy' => [ 'Facility Design', 'Slotting Optimisation', 'Automation Consulting' ],
					]],
					[ 'slug' => 'picking-speed',    'label' => 'Improve order picking speed', 'solutions' => [
						'software'    => [ 'WMS (Warehouse Management Systems)', 'WES (Warehouse Execution Systems)', 'Route Optimisation' ],
						'hardware'    => [ 'AMRs', 'AGVs', 'Conveyors', 'Barcode Scanners' ],
						'consultancy' => [ 'Slotting Optimisation', 'Automation Consulting' ],
					]],
					[ 'slug' => 'inv-accuracy',     'label' => 'Improve warehouse inventory accuracy', 'solutions' => [
						'software'    => [ 'WMS (Warehouse Management Systems)', 'WES (Warehouse Execution Systems)', 'Inventory Management (IM)', 'Visual Search AI' ],
						'hardware'    => [ 'Barcode Scanners', 'RFID Tags', 'High-res Cameras for Part Scanning' ],
						'consultancy' => [ 'Facility Design', 'Slotting Optimisation', 'Master Data Management (MDM) Consulting' ],
					]],
					[ 'slug' => 'warehouse-auto',   'label' => 'Introduce warehouse automation', 'solutions' => [
						'software'    => [ 'WMS (Warehouse Management Systems)', 'WES (Warehouse Execution Systems)' ],
						'hardware'    => [ 'AS/RS', 'AMRs', 'AGVs', 'Conveyors' ],
						'consultancy' => [ 'Automation Consulting', 'Facility Design' ],
					]],
					[ 'slug' => 'handling-costs',   'label' => 'Reduce material handling costs', 'solutions' => [
						'software'    => [ 'WMS (Warehouse Management Systems)', 'WES (Warehouse Execution Systems)', 'Route Optimisation' ],
						'hardware'    => [ 'AMRs', 'AGVs', 'Conveyors' ],
						'consultancy' => [ 'Slotting Optimisation', 'Automation Consulting', 'Facility Design' ],
					]],
				],
			],
			'distribution' => [
				'label'    => 'Distribution & Last-Mile',
				'problems' => [
					[ 'slug' => 'freight-costs',     'label' => 'Reduce freight costs', 'solutions' => [
						'software'    => [ 'TMS (Transportation Management Systems)', 'Route Optimisation', 'Freight Audit Software' ],
						'hardware'    => [ 'Electronic Logging Devices (ELD)' ],
						'consultancy' => [ 'Network Optimisation', 'Carrier Negotiation' ],
					]],
					[ 'slug' => 'critical-delivery', 'label' => 'Improve on-time delivery for critical parts', 'solutions' => [
						'software'    => [ 'TMS (Transportation Management Systems)', 'Route Optimisation' ],
						'hardware'    => [ 'Delivery Drones', 'Electronic Logging Devices (ELD)' ],
						'consultancy' => [ 'Network Optimisation', 'Carrier Negotiation' ],
					]],
					[ 'slug' => 'tracking-vis',      'label' => 'Improve shipment tracking visibility', 'solutions' => [
						'software'    => [ 'TMS (Transportation Management Systems)', 'Route Optimisation', 'Freight Audit Software', 'Inventory Management (IM)' ],
						'hardware'    => [ 'Delivery Drones', 'Electronic Logging Devices (ELD)' ],
						'consultancy' => [ 'Network Optimisation' ],
					]],
					[ 'slug' => 'cross-border',      'label' => 'Simplify cross-border logistics', 'solutions' => [
						'software'    => [ 'TMS (Transportation Management Systems)', 'Freight Audit Software', 'Contract Lifecycle Management (CLM)' ],
						'hardware'    => [],
						'consultancy' => [ 'Network Optimisation', 'Strategic Sourcing Consulting' ],
					]],
					[ 'slug' => 'route-density',     'label' => 'Optimise delivery route density', 'solutions' => [
						'software'    => [ 'TMS (Transportation Management Systems)', 'Route Optimisation' ],
						'hardware'    => [ 'Electronic Logging Devices (ELD)' ],
						'consultancy' => [ 'Network Optimisation' ],
					]],
				],
			],
			'strategic-sourcing' => [
				'label'    => 'Strategic Sourcing',
				'problems' => [
					[ 'slug' => 'supplier-risk', 'label' => 'Reduce supplier and supply chain risk', 'solutions' => [
						'software'    => [ 'E-Sourcing Platforms', 'Contract Lifecycle Management (CLM)', 'Supplier Risk Management', 'Freight Audit Software' ],
						'hardware'    => [],
						'consultancy' => [ 'Strategic Sourcing Consulting', 'Supply Chain Resilience Audits' ],
					]],
					[ 'slug' => 'sc-visibility', 'label' => 'Improve end-to-end supply chain visibility', 'solutions' => [
						'software'    => [ 'E-Sourcing Platforms', 'Supplier Risk Management', 'TMS (Transportation Management Systems)', 'Supply Chain Planning' ],
						'hardware'    => [],
						'consultancy' => [ 'Strategic Sourcing Consulting', 'Supply Chain Resilience Audits', 'Master Data Management (MDM) Consulting' ],
					]],
					[ 'slug' => 'single-source', 'label' => 'Eliminate single-source dependencies', 'solutions' => [
						'software'    => [ 'E-Sourcing Platforms', 'Supplier Risk Management', 'Contract Lifecycle Management (CLM)' ],
						'hardware'    => [],
						'consultancy' => [ 'Strategic Sourcing Consulting', 'Supply Chain Resilience Audits', 'Should-Cost Modelling' ],
					]],
					[ 'slug' => 'raw-mat-costs', 'label' => 'Manage raw material cost volatility', 'solutions' => [
						'software'    => [ 'E-Sourcing Platforms', 'Contract Lifecycle Management (CLM)' ],
						'hardware'    => [],
						'consultancy' => [ 'Strategic Sourcing Consulting', 'Should-Cost Modelling' ],
					]],
					[ 'slug' => 'sc-compliance', 'label' => 'Strengthen supply chain compliance', 'solutions' => [
						'software'    => [ 'Contract Lifecycle Management (CLM)', 'Supplier Risk Management', 'Freight Audit Software' ],
						'hardware'    => [ 'Electronic Logging Devices (ELD)' ],
						'consultancy' => [ 'Strategic Sourcing Consulting', 'Supply Chain Resilience Audits', 'Carrier Negotiation' ],
					]],
				],
			],
			'parts-discovery' => [
				'label'    => 'Parts Discovery & Digital',
				'problems' => [
					[ 'slug' => 'parts-id',          'label' => 'Improve spare parts identification', 'solutions' => [
						'software'    => [ 'PIM (Product Information Management)', 'Visual Search AI', 'Interactive 3D Catalogs' ],
						'hardware'    => [ 'High-res Cameras for Part Scanning' ],
						'consultancy' => [ 'Master Data Management (MDM) Consulting', 'UI/UX Design for B2B' ],
					]],
					[ 'slug' => 'parts-catalogue',   'label' => 'Modernise parts catalogues', 'solutions' => [
						'software'    => [ 'PIM (Product Information Management)', 'Interactive 3D Catalogs' ],
						'hardware'    => [ 'High-res Cameras for Part Scanning' ],
						'consultancy' => [ 'Master Data Management (MDM) Consulting', 'UI/UX Design for B2B' ],
					]],
					[ 'slug' => 'digital-buyer-exp', 'label' => 'Improve the digital parts buying experience', 'solutions' => [
						'software'    => [ 'PIM (Product Information Management)', 'Interactive 3D Catalogs', 'Visual Search AI' ],
						'hardware'    => [],
						'consultancy' => [ 'UI/UX Design for B2B', 'Master Data Management (MDM) Consulting' ],
					]],
					[ 'slug' => 'master-data',       'label' => 'Improve parts master data quality', 'solutions' => [
						'software'    => [ 'PIM (Product Information Management)', 'Visual Search AI' ],
						'hardware'    => [ 'High-res Cameras for Part Scanning' ],
						'consultancy' => [ 'Master Data Management (MDM) Consulting' ],
					]],
					[ 'slug' => '3d-vis',            'label' => 'Enable 3D parts visualisation', 'solutions' => [
						'software'    => [ 'Interactive 3D Catalogs', 'Visual Search AI', 'PIM (Product Information Management)' ],
						'hardware'    => [ 'High-res Cameras for Part Scanning', '3D Printers' ],
						'consultancy' => [ 'UI/UX Design for B2B' ],
					]],
				],
			],
			'returns-reman' => [
				'label'    => 'Returns & Remanufacturing',
				'problems' => [
					[ 'slug' => 'reverse-logistics', 'label' => 'Streamline reverse logistics operations', 'solutions' => [
						'software'    => [ 'Reverse Logistics Software (RMA)', 'Core Tracking Systems', 'TMS (Transportation Management Systems)' ],
						'hardware'    => [ 'Testing & Diagnostic Equipment for Cores' ],
						'consultancy' => [ 'Circular Economy Strategy', 'Remanufacturing Process Design' ],
					]],
					[ 'slug' => 'core-tracking',     'label' => 'Improve core tracking and recovery', 'solutions' => [
						'software'    => [ 'Core Tracking Systems', 'Reverse Logistics Software (RMA)', 'Van Stock Software' ],
						'hardware'    => [ 'Testing & Diagnostic Equipment for Cores', 'Barcode Scanners' ],
						'consultancy' => [ 'Remanufacturing Process Design', 'Circular Economy Strategy' ],
					]],
					[ 'slug' => 'reman-yield',       'label' => 'Increase remanufacturing yield', 'solutions' => [
						'software'    => [ 'Core Tracking Systems', 'Reverse Logistics Software (RMA)', 'PIM (Product Information Management)' ],
						'hardware'    => [ 'Testing & Diagnostic Equipment for Cores', '3D Printers' ],
						'consultancy' => [ 'Remanufacturing Process Design', 'Circular Economy Strategy' ],
					]],
					[ 'slug' => 'warranty-claims',   'label' => 'Simplify warranty claim processing', 'solutions' => [
						'software'    => [ 'Reverse Logistics Software (RMA)', 'Core Tracking Systems', 'Contract Lifecycle Management (CLM)' ],
						'hardware'    => [],
						'consultancy' => [ 'Circular Economy Strategy', 'Remanufacturing Process Design' ],
					]],
					[ 'slug' => 'circular-economy',  'label' => 'Improve circular economy and sustainability tracking', 'solutions' => [
						'software'    => [ 'Core Tracking Systems', 'Reverse Logistics Software (RMA)' ],
						'hardware'    => [ 'Testing & Diagnostic Equipment for Cores' ],
						'consultancy' => [ 'Circular Economy Strategy', 'Remanufacturing Process Design' ],
					]],
				],
			],
		],
		'ecommerce' => [
			'platform-arch' => [
				'label'    => 'Platform & Architecture',
				'problems' => [
					[ 'slug' => 'legacy-platform',     'label' => 'Modernise legacy monolithic architecture', 'solutions' => [
						'software'    => [ 'Headless Commerce Platforms', 'API Gateways', 'Microservices Architecture' ],
						'hardware'    => [],
						'consultancy' => [ 'E-commerce Architecture Consulting', 'Cloud Migration', 'Systems Integration' ],
					]],
					[ 'slug' => 'erp-crm-integration', 'label' => 'Improve ERP and CRM integration', 'solutions' => [
						'software'    => [ 'API Gateways', 'Headless Commerce Platforms', 'CRM', 'CDP (Customer Data Platform)', 'Microservices Architecture' ],
						'hardware'    => [],
						'consultancy' => [ 'Systems Integration', 'E-commerce Architecture Consulting', 'Data Governance Consulting' ],
					]],
					[ 'slug' => 'deployment-speed',    'label' => 'Accelerate deployment and release cycles', 'solutions' => [
						'software'    => [ 'Headless Commerce Platforms', 'API Gateways', 'Microservices Architecture' ],
						'hardware'    => [],
						'consultancy' => [ 'E-commerce Architecture Consulting', 'Cloud Migration' ],
					]],
					[ 'slug' => 'scalability',         'label' => 'Improve platform scalability', 'solutions' => [
						'software'    => [ 'Headless Commerce Platforms', 'Microservices Architecture', 'API Gateways', 'Cloud Data Warehouses' ],
						'hardware'    => [],
						'consultancy' => [ 'E-commerce Architecture Consulting', 'Cloud Migration' ],
					]],
					[ 'slug' => 'security-vulns',      'label' => 'Strengthen platform security', 'solutions' => [
						'software'    => [ 'Headless Commerce Platforms', 'API Gateways', 'Identity Verification', 'B2B Payment Gateways' ],
						'hardware'    => [],
						'consultancy' => [ 'E-commerce Architecture Consulting', 'Systems Integration', 'PCI Compliance Audits', 'Fraud Mitigation Strategy' ],
					]],
				],
			],
			'cx-conversion' => [
				'label'    => 'CX & Conversion',
				'problems' => [
					[ 'slug' => 'b2b-buying',           'label' => 'Simplify B2B buying journeys', 'solutions' => [
						'software'    => [ 'CPQ (Configure, Price, Quote)', 'Personalisation Engines', 'Chatbots', 'Headless Commerce Platforms', '3D Configurators' ],
						'hardware'    => [],
						'consultancy' => [ 'B2B Buyer Journey Mapping', 'UX/UI Design', 'CRO (Conversion Rate Optimisation)' ],
					]],
					[ 'slug' => 'quoting',              'label' => 'Automate quoting with CPQ', 'solutions' => [
						'software'    => [ 'CPQ (Configure, Price, Quote)', '3D Configurators', 'Chatbots' ],
						'hardware'    => [],
						'consultancy' => [ 'B2B Buyer Journey Mapping', 'UX/UI Design', 'Systems Integration' ],
					]],
					[ 'slug' => 'site-search',          'label' => 'Improve product search and discovery', 'solutions' => [
						'software'    => [ 'Personalisation Engines', '3D Configurators', 'Visual Search AI', 'Predictive Analytics' ],
						'hardware'    => [],
						'consultancy' => [ 'UX/UI Design', 'CRO (Conversion Rate Optimisation)' ],
					]],
					[ 'slug' => 'b2b-conversion',       'label' => 'Increase B2B conversion rates', 'solutions' => [
						'software'    => [ 'CPQ (Configure, Price, Quote)', 'Personalisation Engines', 'Chatbots', '3D Configurators' ],
						'hardware'    => [],
						'consultancy' => [ 'CRO (Conversion Rate Optimisation)', 'UX/UI Design', 'B2B Buyer Journey Mapping' ],
					]],
					[ 'slug' => 'personalised-pricing', 'label' => 'Enable personalised and contract pricing', 'solutions' => [
						'software'    => [ 'CPQ (Configure, Price, Quote)', 'Personalisation Engines', 'Predictive Analytics' ],
						'hardware'    => [],
						'consultancy' => [ 'B2B Buyer Journey Mapping', 'CRO (Conversion Rate Optimisation)', 'AI Strategy' ],
					]],
				],
			],
			'fulfillment' => [
				'label'    => 'Supply Chain & Fulfillment',
				'problems' => [
					[ 'slug' => 'order-visibility', 'label' => 'Improve order visibility across the supply chain', 'solutions' => [
						'software'    => [ 'DOM (Distributed Order Management)', 'Real-Time Visibility Platforms', 'TMS (Transportation Management Systems)', 'API Gateways' ],
						'hardware'    => [ 'RFID Tags' ],
						'consultancy' => [ 'Fulfilment Network Design', 'Systems Integration' ],
					]],
					[ 'slug' => 'shipment-delays',  'label' => 'Reduce shipment delays', 'solutions' => [
						'software'    => [ 'DOM (Distributed Order Management)', 'Real-Time Visibility Platforms', 'TMS (Transportation Management Systems)', 'WES (Warehouse Execution Systems)' ],
						'hardware'    => [ 'Automated Dimensioning Systems' ],
						'consultancy' => [ 'Fulfilment Network Design', '3PL Selection' ],
					]],
					[ 'slug' => 'inv-levels',       'label' => 'Improve inventory accuracy', 'solutions' => [
						'software'    => [ 'DOM (Distributed Order Management)', 'WES (Warehouse Execution Systems)', 'Real-Time Visibility Platforms', 'Cloud Data Warehouses' ],
						'hardware'    => [ 'RFID Tags', 'Automated Dimensioning Systems' ],
						'consultancy' => [ 'Fulfilment Network Design', 'Systems Integration' ],
					]],
					[ 'slug' => 'fulfil-costs',     'label' => 'Reduce fulfilment costs', 'solutions' => [
						'software'    => [ 'DOM (Distributed Order Management)', 'WES (Warehouse Execution Systems)', 'TMS (Transportation Management Systems)' ],
						'hardware'    => [ 'Automated Dimensioning Systems' ],
						'consultancy' => [ 'Fulfilment Network Design', '3PL Selection' ],
					]],
					[ 'slug' => 'returns-mgmt',     'label' => 'Streamline returns management', 'solutions' => [
						'software'    => [ 'DOM (Distributed Order Management)', 'Reverse Logistics Software (RMA)', 'API Gateways' ],
						'hardware'    => [ 'RFID Tags' ],
						'consultancy' => [ 'Fulfilment Network Design', '3PL Selection', 'UX/UI Design' ],
					]],
				],
			],
			'payments-security' => [
				'label'    => 'Payments, Fraud & Security',
				'problems' => [
					[ 'slug' => 'payment-terms',      'label' => 'Simplify B2B payment terms management', 'solutions' => [
						'software'    => [ 'B2B Payment Gateways', 'Fraud Detection (Machine Learning)', 'Identity Verification', 'CPQ (Configure, Price, Quote)' ],
						'hardware'    => [],
						'consultancy' => [ 'PCI Compliance Audits', 'Fraud Mitigation Strategy' ],
					]],
					[ 'slug' => 'fraud-risk',         'label' => 'Reduce payment fraud risk', 'solutions' => [
						'software'    => [ 'Fraud Detection (Machine Learning)', 'Identity Verification', 'B2B Payment Gateways' ],
						'hardware'    => [],
						'consultancy' => [ 'PCI Compliance Audits', 'Fraud Mitigation Strategy' ],
					]],
					[ 'slug' => 'invoicing',          'label' => 'Automate invoicing and collections', 'solutions' => [
						'software'    => [ 'B2B Payment Gateways', 'Fraud Detection (Machine Learning)', 'API Gateways' ],
						'hardware'    => [],
						'consultancy' => [ 'PCI Compliance Audits', 'Systems Integration', 'Data Governance Consulting' ],
					]],
					[ 'slug' => 'payment-options',    'label' => 'Offer flexible B2B payment options', 'solutions' => [
						'software'    => [ 'B2B Payment Gateways', 'Identity Verification' ],
						'hardware'    => [],
						'consultancy' => [ 'PCI Compliance Audits', 'Fraud Mitigation Strategy', 'B2B Buyer Journey Mapping' ],
					]],
					[ 'slug' => 'payment-compliance', 'label' => 'Achieve and maintain payment security compliance', 'solutions' => [
						'software'    => [ 'B2B Payment Gateways', 'Fraud Detection (Machine Learning)', 'Identity Verification' ],
						'hardware'    => [],
						'consultancy' => [ 'PCI Compliance Audits', 'Fraud Mitigation Strategy' ],
					]],
				],
			],
			'data-ai' => [
				'label'    => 'Data, AI & Analytics',
				'problems' => [
					[ 'slug' => 'siloed-data',       'label' => 'Unify and activate customer data', 'solutions' => [
						'software'    => [ 'CDP (Customer Data Platform)', 'Predictive Analytics', 'Cloud Data Warehouses' ],
						'hardware'    => [],
						'consultancy' => [ 'Data Governance Consulting', 'AI Strategy' ],
					]],
					[ 'slug' => 'demand-forecast',   'label' => 'Improve demand forecast accuracy', 'solutions' => [
						'software'    => [ 'Predictive Analytics', 'CDP (Customer Data Platform)', 'Cloud Data Warehouses' ],
						'hardware'    => [],
						'consultancy' => [ 'AI Strategy', 'Data Governance Consulting', 'LLM Implementation' ],
					]],
					[ 'slug' => 'personalisation',   'label' => 'Deliver personalised buyer experiences', 'solutions' => [
						'software'    => [ 'CDP (Customer Data Platform)', 'Predictive Analytics', 'Personalisation Engines' ],
						'hardware'    => [],
						'consultancy' => [ 'AI Strategy', 'LLM Implementation', 'CRO (Conversion Rate Optimisation)' ],
					]],
					[ 'slug' => 'realtime-insights', 'label' => 'Enable real-time commerce insights', 'solutions' => [
						'software'    => [ 'CDP (Customer Data Platform)', 'Predictive Analytics', 'Cloud Data Warehouses', 'DOM (Distributed Order Management)' ],
						'hardware'    => [ 'RFID Tags' ],
						'consultancy' => [ 'AI Strategy', 'Data Governance Consulting' ],
					]],
					[ 'slug' => 'data-privacy',      'label' => 'Strengthen data privacy compliance', 'solutions' => [
						'software'    => [ 'CDP (Customer Data Platform)', 'Cloud Data Warehouses', 'Identity Verification' ],
						'hardware'    => [],
						'consultancy' => [ 'Data Governance Consulting', 'AI Strategy', 'PCI Compliance Audits' ],
					]],
				],
			],
		],
	];

	// ─── Filter helpers ────────────────────────────────────────────────────────

	/**
	 * Return site slugs matching the requested expertise slugs.
	 * Validates slugs against EXPERTISE_AREAS keys.
	 *
	 * @param string[] $expertise_slugs
	 * @return string[]
	 */
	public static function site_slugs_for_expertise( array $expertise_slugs ): array {
		$valid = array_filter( $expertise_slugs, static fn( $s ) => isset( self::EXPERTISE_AREAS[ $s ] ) );
		return array_values( array_unique( $valid ) );
	}

	/**
	 * Return site slugs where any of the requested industries applies.
	 *
	 * @param string[] $industry_slugs
	 * @return string[]
	 */
	public static function site_slugs_for_industry( array $industry_slugs ): array {
		$matched = [];
		foreach ( self::SITE_INDUSTRIES as $site_slug => $industries ) {
			foreach ( $industry_slugs as $wanted ) {
				if ( in_array( $wanted, $industries, true ) ) {
					$matched[] = $site_slug;
					break;
				}
			}
		}
		return array_values( array_unique( $matched ) );
	}

	/**
	 * Resolve site slugs → blog_ids by querying wp_blogs.
	 * Returns only blog_ids that exist in the network.
	 *
	 * @param string[] $site_slugs
	 * @return int[]
	 */
	public static function blog_ids_for_site_slugs( array $site_slugs ): array {
		global $wpdb;

		if ( empty( $site_slugs ) ) {
			return [];
		}

		$blog_ids = [];
		foreach ( $site_slugs as $slug ) {
			// Blog path is '/{slug}/' on WordPress Multisite.
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT blog_id FROM {$wpdb->blogs} WHERE path = %s OR path = %s LIMIT 1",
					"/{$slug}/",
					"/{$slug}"
				)
			);
			if ( $row ) {
				$blog_ids[] = (int) $row->blog_id;
			}
		}

		return array_values( array_unique( $blog_ids ) );
	}

	// ─── Card annotation ───────────────────────────────────────────────────────

	/**
	 * Get expertise and industry tags for a given blog_id.
	 * Used to annotate provider cards in the buyer directory.
	 *
	 * Portfolio providers (blog_id = 0) span all expertise areas and industries.
	 *
	 * @return array{ expertise: array<string,string>, industries: array<string,string> }
	 */
	public static function tags_for_blog_id( int $blog_id ): array {
		if ( $blog_id <= 0 ) {
			return [
				'expertise'  => self::EXPERTISE_AREAS,
				'industries' => self::INDUSTRY_LABELS,
			];
		}

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT path FROM {$wpdb->blogs} WHERE blog_id = %d LIMIT 1",
			$blog_id
		) );

		if ( ! $row ) {
			return [ 'expertise' => [], 'industries' => [] ];
		}

		$path_slug = trim( (string) $row->path, '/' );

		$matched_site_slug = null;
		foreach ( array_keys( self::EXPERTISE_AREAS ) as $site_slug ) {
			// Path may be bare slug or nested path ending in slug
			if ( $path_slug === $site_slug || str_ends_with( $path_slug, "/{$site_slug}" ) ) {
				$matched_site_slug = $site_slug;
				break;
			}
		}

		if ( ! $matched_site_slug ) {
			return [ 'expertise' => [], 'industries' => [] ];
		}

		$expertise      = [ $matched_site_slug => self::EXPERTISE_AREAS[ $matched_site_slug ] ];
		$industry_slugs = self::SITE_INDUSTRIES[ $matched_site_slug ] ?? [];
		$industries     = array_intersect_key( self::INDUSTRY_LABELS, array_flip( $industry_slugs ) );

		return compact( 'expertise', 'industries' );
	}
}

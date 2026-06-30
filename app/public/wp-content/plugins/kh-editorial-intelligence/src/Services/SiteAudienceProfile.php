<?php

namespace KH\Editorial\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Centralised site audience profiles.
 *
 * Provides reader personas, editorial pillars, and audience context strings
 * for every target publication in the network. Designed as a single source of
 * truth for LLM prompts across rewrite, research, framework, draft, and SEO
 * agents.
 *
 * Usage:
 *   $context = SiteAudienceProfile::get_audience_context( 'field-service' );
 *   // Returns a prompt-ready paragraph describing readers and coverage.
 */
class SiteAudienceProfile {

    /**
     * Full audience profiles keyed by site slug (matches AllocationService::TARGET_SITES).
     */
    const AUDIENCE = [
        'field-service' => [
            'label'   => 'Field Service',
            'pillars' => [
                [
                    'name'     => 'Field Service Delivery',
                    'core_content_channels' => ['Maintenance Strategy & Execution', 'Service Delivery & Customer Experience'],
                    'focus'    => 'Execution of on-site tasks, technical precision, and the operational excellence required to complete service calls effectively.',
                    'readers'  => ['VP of Service', 'Field Operations Manager', 'Service Director / Chief Service Officer (CSO)', 'Dispatch & Routing Director', 'Customer Success Manager', 'Warranty Operations Director', 'Technical Support Director', 'Fleet Manager', 'VP of Customer Experience (CX)'],
                    'keywords' => ['first-time fix rate', 'service dispatch', 'technician productivity', 'SLAs', 'work order management', 'dispatch routing', 'field service automation', 'triage', 'remote assistance', 'service lifecycle'],
                ],
                [
                    'name'     => 'Mobile Workforce Management',
                    'core_content_channels' => ['Scheduling, Dispatch & Logistics', 'Workforce Operations & Talent'],
                    'focus'    => 'Optimization of field personnel, scheduling, routing, and real-time communication tools for decentralized teams.',
                    'readers'  => ['VP of Service', 'Field Operations Manager', 'Service Director / Chief Service Officer (CSO)', 'Dispatch & Routing Director', 'Customer Success Manager', 'Warranty Operations Director', 'Technical Support Director', 'Fleet Manager', 'VP of Customer Experience (CX)'],
                    'keywords' => ['routing optimization', 'scheduling software', 'gig economy', 'technician training', 'mobile field execution', 'workforce capacity planning', 'dynamic scheduling', 'field service mobility', 'technician retention', 'health and safety'],
                ],
                [
                    'name'     => 'Field Service Profitability',
                    'core_content_channels' => ['Operational Visibility & KPIs'],
                    'focus'    => 'Cost-to-serve, revenue leakage, and the financial performance of service operations.',
                    'readers'  => ['VP of Service', 'Field Operations Manager', 'Service Director / Chief Service Officer (CSO)', 'Dispatch & Routing Director', 'Customer Success Manager', 'Warranty Operations Director', 'Technical Support Director', 'Fleet Manager', 'VP of Customer Experience (CX)'],
                    'keywords' => ['cost-to-serve', 'revenue leakage', 'warranty recovery', 'margin management', 'service contract profitability', 'billing automation', 'parts margin', 'upselling in the field', 'quote-to-cash'],
                ],
                [
                    'name'     => 'Customer Experience & Success',
                    'core_content_channels' => ['Service Delivery & Customer Experience'],
                    'focus'    => 'End-to-end customer journey, relationship management, and driving long-term loyalty through superior service outcomes.',
                    'readers'  => ['VP of Service', 'Field Operations Manager', 'Service Director / Chief Service Officer (CSO)', 'Dispatch & Routing Director', 'Customer Success Manager', 'Warranty Operations Director', 'Technical Support Director', 'Fleet Manager', 'VP of Customer Experience (CX)'],
                    'keywords' => ['Net Promoter Score (NPS)', 'customer retention', 'service transparency', 'loyalty', 'customer self-service portals', 'proactive service', 'customer satisfaction (CSAT)', 'voice of the customer (VoC)', 'real-time ETA tracking'],
                ],
            ],
        ],

        'spare-parts' => [
            'label'   => 'Spare Parts & Logistics',
            'pillars' => [
                [
                    'name'     => 'Network Design & Infrastructure',
                    'core_content_channels' => ['Distribution & Last-Mile Logistics', 'Strategic Sourcing & Supply Resilience'],
                    'focus'    => 'Strategic layout, physical distribution networks, and capital projects for high-performance logistics.',
                    'readers'  => ['Supply Chain Director / VP of Supply Chain', 'Inventory Planning Manager', 'VP of Logistics', 'Warehouse Operations Director', 'Director of Global Distribution', 'Materials Manager', 'Reverse Logistics Manager', 'Transportation Manager', 'Procurement Director'],
                    'keywords' => ['supply chain resilience', 'logistics infrastructure', 'multimodal transport', 'network optimization', 'freight forwarding', 'distribution center design', 'cross-border logistics', 'supply chain mapping', '3PL management'],
                ],
                [
                    'name'     => 'Warehousing & Operations',
                    'core_content_channels' => ['Warehouse Operations & Physical Footprint'],
                    'focus'    => 'Day-to-day warehouse efficiency, picking/packing accuracy, and high-velocity throughput.',
                    'readers'  => ['Supply Chain Director / VP of Supply Chain', 'Inventory Planning Manager', 'VP of Logistics', 'Warehouse Operations Director', 'Director of Global Distribution', 'Materials Manager', 'Reverse Logistics Manager', 'Transportation Manager', 'Procurement Director'],
                    'keywords' => ['warehousing efficiency', 'automated storage (AS/RS)', 'picking accuracy', 'throughput', 'cross-docking', 'warehouse management systems (WMS)', 'material handling equipment', 'labor management', 'reverse logistics operations'],
                ],
                [
                    'name'     => 'Inventory & Digital Tracking',
                    'core_content_channels' => ['Inventory Planning & Demand Intelligence', 'Parts Discovery & Digital Enablement'],
                    'focus'    => 'Real-time visibility, sensor integration, and digital systems for predictive inventory management.',
                    'readers'  => ['Supply Chain Director / VP of Supply Chain', 'Inventory Planning Manager', 'VP of Logistics', 'Warehouse Operations Director', 'Director of Global Distribution', 'Materials Manager', 'Reverse Logistics Manager', 'Transportation Manager', 'Procurement Director'],
                    'keywords' => ['demand forecasting', 'real-time visibility', 'safety stock', 'inventory turnover', 'multi-echelon inventory optimization', 'stockouts', 'carrying costs', 'vendor-managed inventory (VMI)', 'parts supersession', 'ABC analysis'],
                ],
                [
                    'name'     => 'Lifecycle Service & Regulations',
                    'core_content_channels' => ['Returns, Cores & Remanufacturing'],
                    'focus'    => 'Regulatory landscape, parts availability over the asset lifecycle, and compliance-driven service models.',
                    'readers'  => ['Supply Chain Director / VP of Supply Chain', 'Inventory Planning Manager', 'VP of Logistics', 'Warehouse Operations Director', 'Director of Global Distribution', 'Materials Manager', 'Reverse Logistics Manager', 'Transportation Manager', 'Procurement Director'],
                    'keywords' => ['RoHS compliance', 'parts obsolescence', 'reverse logistics', 'sustainability regs', 'circular supply chain', 'end-of-life management', 'waste regulations', 'REACH compliance', 'export controls', 'dangerous goods handling'],
                ],
            ],
        ],

        'pricing' => [
            'label'   => 'B2B Pricing',
            'pillars' => [
                [
                    'name'     => 'Pricing Strategy & Tactics',
                    'core_content_channels' => ['Commercial Strategy'],
                    'focus'    => 'Structural development of pricing models, competitive positioning, and market entry strategies.',
                    'readers'  => ['VP of Pricing / Pricing Director', 'Revenue Operations (RevOps) Leader', 'Chief Financial Officer (CFO)', 'Director of Commercial Strategy', 'Deal Desk Manager', 'VP of Sales Operations', 'Chief Commercial Officer (CCO)', 'Margin & Profitability Analyst', 'Commercial Finance Director'],
                    'keywords' => ['price positioning', 'competitive intelligence', 'value-based pricing', 'price waterfall', 'pricing psychology', 'pricing architecture', 'portfolio pricing', 'market segmentation', 'tier-based pricing', 'list price management'],
                ],
                [
                    'name'     => 'Price Optimization & Analytics',
                    'core_content_channels' => ['Price & Margin Excellence', 'Revenue Intelligence'],
                    'focus'    => 'Operational use of data to adjust pricing dynamically and improve margin performance.',
                    'readers'  => ['VP of Pricing / Pricing Director', 'Revenue Operations (RevOps) Leader', 'Chief Financial Officer (CFO)', 'Director of Commercial Strategy', 'Deal Desk Manager', 'VP of Sales Operations', 'Chief Commercial Officer (CCO)', 'Margin & Profitability Analyst', 'Commercial Finance Director'],
                    'keywords' => ['dynamic pricing', 'margin analytics', 'price elasticity', 'revenue modeling', 'algorithmic pricing', 'price scraping', 'willingness-to-pay', 'discount management', 'CPQ (Configure Price Quote)', 'price variance analysis'],
                ],
                [
                    'name'     => 'Contract & Deal Management',
                    'core_content_channels' => ['Rebate & Incentive Mgmt'],
                    'focus'    => 'Digital infrastructure required to manage complex B2B agreements, renewals, and compliance.',
                    'readers'  => ['VP of Pricing / Pricing Director', 'Revenue Operations (RevOps) Leader', 'Chief Financial Officer (CFO)', 'Director of Commercial Strategy', 'Deal Desk Manager', 'VP of Sales Operations', 'Chief Commercial Officer (CCO)', 'Margin & Profitability Analyst', 'Commercial Finance Director'],
                    'keywords' => ['deal desk management', 'contract lifecycle management (CLM)', 'rebate programs', 'volume discounts', 'price negotiation', 'compliance tracking', 'spot pricing', 'long-term agreements', 'terms and conditions'],
                ],
                [
                    'name'     => 'Value-Based Selling & Growth',
                    'core_content_channels' => ['Demand Operations'],
                    'focus'    => 'Selling outcomes, communicating product value, and driving business growth through strategic pricing.',
                    'readers'  => ['VP of Pricing / Pricing Director', 'Revenue Operations (RevOps) Leader', 'Chief Financial Officer (CFO)', 'Director of Commercial Strategy', 'Deal Desk Manager', 'VP of Sales Operations', 'Chief Commercial Officer (CCO)', 'Margin & Profitability Analyst', 'Commercial Finance Director'],
                    'keywords' => ['value-based selling', 'outcome-based pricing', 'psychological pricing', 'revenue growth', 'cross-selling strategies', 'upselling margins', 'price realization', 'share of wallet', 'churn prevention pricing'],
                ],
            ],
        ],

        'ecommerce' => [
            'label'   => 'eCommerce',
            'pillars' => [
                [
                    'name'     => 'Digital Sales Channels',
                    'core_content_channels' => ['Platform & Architecture'],
                    'focus'    => 'Architecture, build, and platform strategy for reaching B2B customers through digital touchpoints.',
                    'readers'  => ['VP of eCommerce', 'Head of Digital Transformation', 'Director of Digital Sales', 'Chief Digital Officer (CDO)', 'Director of B2B User Experience (UX)', 'IT Director (eCommerce & ERP Integration)', 'Product Information Management (PIM) Lead', 'Digital Marketing Director', 'Omnichannel Strategy Lead'],
                    'keywords' => ['omni-channel strategy', 'direct-to-customer (D2C)', 'digital marketplaces', 'B2B portals', 'headless commerce', 'social commerce', 'punchout catalogs', 'dealer networks', 'partner portals'],
                ],
                [
                    'name'     => 'B2B User Experience (UX)',
                    'core_content_channels' => ['Customer Experience (CX) & Conversion'],
                    'focus'    => 'Streamlining the digital interface to improve the buying journey and self-service capabilities.',
                    'readers'  => ['VP of eCommerce', 'Head of Digital Transformation', 'Director of Digital Sales', 'Chief Digital Officer (CDO)', 'Director of B2B User Experience (UX)', 'IT Director (eCommerce & ERP Integration)', 'Product Information Management (PIM) Lead', 'Digital Marketing Director', 'Omnichannel Strategy Lead'],
                    'keywords' => ['self-service portal', 'personalized buying experience', 'mobile commerce', 'B2B procurement', 'catalog management', 'intuitive search', 'faceted navigation', 'quick order pads', 'frictionless checkout', 'customized pricing views'],
                ],
                [
                    'name'     => 'eCommerce Tech Stack & Integration',
                    'core_content_channels' => ['Payments, Fraud & Security', 'Supply Chain & Fulfillment'],
                    'focus'    => 'Digital core, back-end connectivity, and systems integration between eCommerce and ERP platforms.',
                    'readers'  => ['VP of eCommerce', 'Head of Digital Transformation', 'Director of Digital Sales', 'Chief Digital Officer (CDO)', 'Director of B2B User Experience (UX)', 'IT Director (eCommerce & ERP Integration)', 'Product Information Management (PIM) Lead', 'Digital Marketing Director', 'Omnichannel Strategy Lead'],
                    'keywords' => ['ERP/PIM integration', 'API connectivity', 'cloud commerce', 'cybersecurity', 'headless architecture', 'composable commerce', 'digital asset management (DAM)', 'payment gateways', 'order management systems (OMS)'],
                ],
                [
                    'name'     => 'Digital Customer Acquisition & Growth',
                    'core_content_channels' => ['Data, AI & Analytics'],
                    'focus'    => 'Scaling revenue through digital service models and customer-centric growth strategies.',
                    'readers'  => ['VP of eCommerce', 'Head of Digital Transformation', 'Director of Digital Sales', 'Chief Digital Officer (CDO)', 'Director of B2B User Experience (UX)', 'IT Director (eCommerce & ERP Integration)', 'Product Information Management (PIM) Lead', 'Digital Marketing Director', 'Omnichannel Strategy Lead'],
                    'keywords' => ['digital demand gen', 'B2B SEO', 'conversion rate optimization (CRO)', 'lead nurturing', 'account-based marketing (ABM) for eCommerce', 'retargeting', 'cart abandonment recovery', 'digital ROI', 'customer acquisition cost (CAC)'],
                ],
            ],
        ],

        'aftermarket' => [
            'label'   => 'Aftermarket',
            'pillars' => [
                [
                    'name'     => 'Service Revenue Streams',
                    'core_content_channels' => ['Aftermarket Profitability & Growth', 'Customer Loyalty & Brand Experience'],
                    'focus'    => 'Strategy behind creating sustainable, recurring revenue models within the aftermarket sector.',
                    'readers'  => ['VP of Aftermarket Operations', 'Customer Success Manager', 'Service Contract Manager', 'Parts Operations Director', 'Warranty & Repair Manager', 'Quality Assurance Director', 'Aftermarket Sales Director', 'Product Support Manager', 'Service Delivery Manager', 'Customer Experience Director (Aftermarket)'],
                    'keywords' => ['recurring revenue models', 'service contracts', 'subscription tiers', 'spare parts profitability', 'aftermarket capture rate', 'attach rates', 'extended warranties', 'monetization strategies', 'service upgrades'],
                ],
                [
                    'name'     => 'Servitization & Advanced Service Strategies',
                    'core_content_channels' => ['Service Business Models & Servitization'],
                    'focus'    => 'Moving toward outcome-based service models and business model innovation.',
                    'readers'  => ['VP of Aftermarket Operations', 'Customer Success Manager', 'Service Contract Manager', 'Parts Operations Director', 'Warranty & Repair Manager', 'Quality Assurance Director', 'Aftermarket Sales Director', 'Product Support Manager', 'Service Delivery Manager', 'Customer Experience Director (Aftermarket)'],
                    'keywords' => ['outcomes-as-a-service', 'performance-based contracts', 'digital twins', 'uptime guarantees', 'equipment-as-a-service (EaaS)', 'risk-sharing contracts', 'predictive maintenance services', 'value-added services'],
                ],
                [
                    'name'     => 'Installed Base Intelligence',
                    'core_content_channels' => ['Aftermarket Digital Transformation'],
                    'focus'    => 'Utilizing data and connectivity to gain actionable insights into customer assets.',
                    'readers'  => ['VP of Aftermarket Operations', 'Customer Success Manager', 'Service Contract Manager', 'Parts Operations Director', 'Warranty & Repair Manager', 'Quality Assurance Director', 'Aftermarket Sales Director', 'Product Support Manager', 'Service Delivery Manager', 'Customer Experience Director (Aftermarket)'],
                    'keywords' => ['asset monitoring', 'remote diagnostics', 'equipment lifecycle tracking', 'installed base visibility', 'product registration', 'telemetry data', 'IoT asset tracking', 'asset health scoring', 'retrofit campaigns'],
                ],
                [
                    'name'     => 'Installed Base Lifecycle Management',
                    'core_content_channels' => ['Installed Base Lifecycle Management'],
                    'focus'    => 'Operational maintenance and distribution of components throughout the product life cycle.',
                    'readers'  => ['VP of Aftermarket Operations', 'Customer Success Manager', 'Service Contract Manager', 'Parts Operations Director', 'Warranty & Repair Manager', 'Quality Assurance Director', 'Aftermarket Sales Director', 'Product Support Manager', 'Service Delivery Manager', 'Customer Experience Director (Aftermarket)'],
                    'keywords' => ['maintenance intervals', 'part replenishment', 'OEM vs. aftermarket parts', 'cannibalization', 'remanufacturing', 'core tracking', 'predictive parts ordering', 'service bill of materials (sBOM)', 'parts interchangeability'],
                ],
            ],
        ],

        'aerospace' => [
            'label'   => 'Aviation & Aerospace',
            'pillars' => [
                [
                    'name'     => 'Procurement & Engineering',
                    'core_content_channels' => ['Production Backlogs'],
                    'focus'    => 'Design, development, and supply chain inputs required for high-precision aerospace projects.',
                    'readers'  => ['VP of MRO Operations', 'Aerospace Supply Chain Director', 'Director of Fleet Engineering', 'Aviation Regulatory Compliance Officer', 'Chief Mechanic / Chief Engineer', 'Quality Assurance Director', 'Procurement Head (Aviation)', 'VP of Aerospace R&D', 'Airline Operations Director'],
                    'keywords' => ['supply chain transparency', 'aerospace components', 'lean engineering', 'defense procurement', 'strategic sourcing', 'supplier risk management', 'raw material forecasting', 'AS9100 standards', 'composite materials'],
                ],
                [
                    'name'     => 'Maintenance, Repair & Overhaul (MRO)',
                    'core_content_channels' => ['Commercial MRO', 'Skills Gaps'],
                    'focus'    => 'Operational requirements for aircraft safety, reliability, and service maintenance.',
                    'readers'  => ['VP of MRO Operations', 'Aerospace Supply Chain Director', 'Director of Fleet Engineering', 'Aviation Regulatory Compliance Officer', 'Chief Mechanic / Chief Engineer', 'Quality Assurance Director', 'Procurement Head (Aviation)', 'VP of Aerospace R&D', 'Airline Operations Director'],
                    'keywords' => ['aircraft uptime', 'heavy maintenance', 'component repair', 'predictive maintenance', 'line maintenance', 'engine overhaul', 'AOG (Aircraft on Ground) support', 'turnaround time (TAT)', 'rotables management', 'non-destructive testing (NDT)'],
                ],
                [
                    'name'     => 'Digital Innovation & Tech',
                    'core_content_channels' => ['Autonomous Systems'],
                    'focus'    => 'Integration of advanced diagnostic tools and digital connectivity to drive operational efficiency.',
                    'readers'  => ['VP of MRO Operations', 'Aerospace Supply Chain Director', 'Director of Fleet Engineering', 'Aviation Regulatory Compliance Officer', 'Chief Mechanic / Chief Engineer', 'Quality Assurance Director', 'Procurement Head (Aviation)', 'VP of Aerospace R&D', 'Airline Operations Director'],
                    'keywords' => ['digital twins', 'diagnostic tech', 'additive manufacturing in aerospace', 'advanced air mobility (AAM)', 'drone technology', 'sustainable aviation fuel (SAF)', 'avionics upgrades', 'electrification'],
                ],
                [
                    'name'     => 'Regulation & Growth',
                    'core_content_channels' => ['Geopolitical/Regulatory', 'Decarbonization'],
                    'focus'    => 'Meeting stringent aviation standards while scaling service-driven growth.',
                    'readers'  => ['VP of MRO Operations', 'Aerospace Supply Chain Director', 'Director of Fleet Engineering', 'Aviation Regulatory Compliance Officer', 'Chief Mechanic / Chief Engineer', 'Quality Assurance Director', 'Procurement Head (Aviation)', 'VP of Aerospace R&D', 'Airline Operations Director'],
                    'keywords' => ['ASA/FAA compliance', 'aviation standards', 'safety culture', 'growth metrics', 'airworthiness directives', 'safety management systems (SMS)', 'certification processes', 'carbon offset regulations', 'quality assurance'],
                ],
            ],
        ],

        'built-env' => [
            'label'   => 'Built Environment',
            'pillars' => [
                [
                    'name'     => 'Construction & Development',
                    'core_content_channels' => ['Digital Project Delivery', 'Transport & Connectivity Infrastructure', 'Health, Safety & Workforce Wellbeing'],
                    'focus'    => 'Build phase, encompassing infrastructure projects and the development of new physical assets.',
                    'readers'  => ['VP of Facilities Management', 'Infrastructure Project Director', 'Director of Smart Buildings', 'Chief Sustainability Officer (CSO)', 'VP of Real Estate Operations', 'Construction Management Lead', 'Energy Performance Manager', 'Chief Operations Officer (COO - Real Estate)', 'BIM / VDC Manager'],
                    'keywords' => ['BIM', 'infrastructure project management', 'site safety', 'design-build', 'prefabrication', 'modular construction', 'heavy civil engineering', 'lean construction', 'project lifecycle management', 'cost estimation'],
                ],
                [
                    'name'     => 'Maintenance, Facilities & Asset Management',
                    'core_content_channels' => ['Infrastructure & Physical Asset Management'],
                    'focus'    => 'Day-to-day operation, maintenance, and performance of existing buildings and infrastructure.',
                    'readers'  => ['VP of Facilities Management', 'Infrastructure Project Director', 'Director of Smart Buildings', 'Chief Sustainability Officer (CSO)', 'VP of Real Estate Operations', 'Construction Management Lead', 'Energy Performance Manager', 'Chief Operations Officer (COO - Real Estate)', 'BIM / VDC Manager'],
                    'keywords' => ['preventive maintenance', 'building operations', 'asset management', 'tenant experience', 'CAFM', 'space utilization', 'predictive cleaning', 'HVAC management', 'integrated facilities management (IFM)'],
                ],
                [
                    'name'     => 'Smart Technology & Integration',
                    'core_content_channels' => ['ASmart Buildings & Facilities Management'],
                    'focus'    => 'Digital layer including IoT and building management systems that enable data-driven performance.',
                    'readers'  => ['VP of Facilities Management', 'Infrastructure Project Director', 'Director of Smart Buildings', 'Chief Sustainability Officer (CSO)', 'VP of Real Estate Operations', 'Construction Management Lead', 'Energy Performance Manager', 'Chief Operations Officer (COO - Real Estate)', 'BIM / VDC Manager'],
                    'keywords' => ['IoT sensors', 'BMS', 'smart lighting', 'energy-efficient building automation', 'proptech', 'smart HVAC', 'access control systems', 'digital building twins', 'occupancy tracking', 'smart grid integration'],
                ],
                [
                    'name'     => 'Sustainability & Infrastructure Performance',
                    'core_content_channels' => ['Circular Infrastructure & Materials'],
                    'focus'    => 'Long-term energy efficiency, carbon targets, and service-based asset optimization.',
                    'readers'  => ['VP of Facilities Management', 'Infrastructure Project Director', 'Director of Smart Buildings', 'Chief Sustainability Officer (CSO)', 'VP of Real Estate Operations', 'Construction Management Lead', 'Energy Performance Manager', 'Chief Operations Officer (COO - Real Estate)', 'BIM / VDC Manager'],
                    'keywords' => ['net-zero buildings', 'energy retrofits', 'LEED certification', 'sustainable infrastructure', 'BREEAM', 'carbon accounting', 'circular building materials', 'waste reduction', 'green roofs', 'energy performance certificates (EPC)'],
                ],
            ],
        ],

        'industrial' => [
            'label'   => 'Industrial Machinery',
            'pillars' => [
                [
                    'name'     => 'Design & Engineering',
                    'core_content_channels' => ['Electrification & Alternative Powertrains'],
                    'focus'    => 'Research, development, and innovation required to design next-generation industrial equipment.',
                    'readers'  => ['VP of Engineering', 'Plant Manager / Factory Director', 'VP of Manufacturing Operations', 'Director of R&D / Product Development', 'Chief Technology Officer (CTO - Hardware)', 'Automation & Robotics Engineer', 'Continuous Improvement Manager', 'Supply Chain Director', 'Quality Control Director'],
                    'keywords' => ['CAD/CAM', 'R&D', 'industrial design', 'prototyping', 'product lifecycle management (PLM)', 'generative design', 'concurrent engineering', 'human-machine interface (HMI)', 'systems engineering', 'mechatronics'],
                ],
                [
                    'name'     => 'Manufacturing Operations',
                    'core_content_channels' => ['Production Ramp-up & Supply Resilience', 'Operational Safety & Ergonomics'],
                    'focus'    => 'Shop-floor execution, production throughput, and efficient operation of industrial assets.',
                    'readers'  => ['VP of Engineering', 'Plant Manager / Factory Director', 'VP of Manufacturing Operations', 'Director of R&D / Product Development', 'Chief Technology Officer (CTO - Hardware)', 'Automation & Robotics Engineer', 'Continuous Improvement Manager', 'Supply Chain Director', 'Quality Control Director'],
                    'keywords' => ['OEE (Overall Equipment Effectiveness)', 'shop floor performance', 'throughput', 'lean manufacturing', 'bottleneck analysis', 'quality control', 'total productive maintenance (TPM)', 'cycle time reduction', 'CNC machining'],
                ],
                [
                    'name'     => 'Industrial Digitalization',
                    'core_content_channels' => ['Connected Equipment & Industrial AI', 'Automation & Autonomous Systems'],
                    'focus'    => 'Digital integration, automation, and connectivity required to modernize industrial production environments.',
                    'readers'  => ['VP of Engineering', 'Plant Manager / Factory Director', 'VP of Manufacturing Operations', 'Director of R&D / Product Development', 'Chief Technology Officer (CTO - Hardware)', 'Automation & Robotics Engineer', 'Continuous Improvement Manager', 'Supply Chain Director', 'Quality Control Director'],
                    'keywords' => ['IIoT', 'automation', 'predictive maintenance', 'robotics', 'SCADA', 'edge computing', 'machine learning', 'vision systems', 'autonomous mobile robots (AMRs)', 'programmable logic controllers (PLC)'],
                ],
                [
                    'name'     => 'Sustainability & Remanufacturing',
                    'core_content_channels' => ['Service Lifecycle Management'],
                    'focus'    => 'Circular economy models, energy efficiency, and product life-extension services.',
                    'readers'  => ['VP of Engineering', 'Plant Manager / Factory Director', 'VP of Manufacturing Operations', 'Director of R&D / Product Development', 'Chief Technology Officer (CTO - Hardware)', 'Automation & Robotics Engineer', 'Continuous Improvement Manager', 'Supply Chain Director', 'Quality Control Director'],
                    'keywords' => ['circular economy', 'remanufacturing', 'carbon-efficient manufacturing', 'energy auditing', 'product life extension', 'core recovery', 'closed-loop supply chain', 'sustainable materials', 'emissions tracking', 'zero waste to landfill'],
                ],
            ],
        ],

        'utilities' => [
            'label'   => 'Energy and Utilities',
            'pillars' => [
                [
                    'name'     => 'Design & Engineering',
                    'core_content_channels' => ['Load Growth & Interconnection Queues'],
                    'focus'    => 'Design and construction of energy assets, water networks, and utility infrastructure.',
                    'readers'  => ['VP of Utility Operations', 'Grid Operations Director', 'Director of Capital Projects', 'Telecom Network Operations Manager', 'Chief Sustainability Officer (CSO)', 'Smart Grid Architect', 'Network Operations Center (NOC) Lead', 'Director of Energy Transition / Renewables', 'Infrastructure Compliance Officer'],
                    'keywords' => ['capital project management', 'utility grid expansion', 'telecom cabling', 'substation design', 'pipeline construction', 'fiber optic deployment', 'regulatory approvals', 'project financing', 'EPC (Engineering, Procurement, Construction)'],
                ],
                [
                    'name'     => 'Asset Operations & Network Management',
                    'core_content_channels' => ['Grid Modernization & Resilience', 'Compliance, Safety & PFAS'],
                    'focus'    => 'Day-to-day reliability, monitoring, and maintenance of essential infrastructure.',
                    'readers'  => ['VP of Utility Operations', 'Grid Operations Director', 'Director of Capital Projects', 'Telecom Network Operations Manager', 'Chief Sustainability Officer (CSO)', 'Smart Grid Architect', 'Network Operations Center (NOC) Lead', 'Director of Energy Transition / Renewables', 'Infrastructure Compliance Officer'],
                    'keywords' => ['reliability', 'remote monitoring', 'grid balancing', 'maintenance management', 'outage management', 'dispatchable generation', 'predictive asset management', 'water treatment operations', 'load forecasting'],
                ],
                [
                    'name'     => 'Digital Grid & Smart Metering',
                    'core_content_channels' => ['AI & Intelligent Operations', 'Workforce Capability & AI Literacy'],
                    'focus'    => 'Digital connectivity layer and smart management of network data for utilities and telecoms.',
                    'readers'  => ['VP of Utility Operations', 'Grid Operations Director', 'Director of Capital Projects', 'Telecom Network Operations Manager', 'Chief Sustainability Officer (CSO)', 'Smart Grid Architect', 'Network Operations Center (NOC) Lead', 'Director of Energy Transition / Renewables', 'Infrastructure Compliance Officer'],
                    'keywords' => ['smart metering', 'grid data analytics', 'connectivity', 'automated utility management', 'advanced metering infrastructure (AMI)', 'grid edge technologies', 'cybersecurity for critical infrastructure', 'geographic information systems (GIS)'],
                ],
                [
                    'name'     => 'Servitization & Sustainability Models',
                    'core_content_channels' => ['Circular Resources & Recycling'],
                    'focus'    => 'Energy transition, decarbonization, and outcome-based service innovation.',
                    'readers'  => ['VP of Utility Operations', 'Grid Operations Director', 'Director of Capital Projects', 'Telecom Network Operations Manager', 'Chief Sustainability Officer (CSO)', 'Smart Grid Architect', 'Network Operations Center (NOC) Lead', 'Director of Energy Transition / Renewables', 'Infrastructure Compliance Officer'],
                    'keywords' => ['energy transition', 'decarbonization', 'water recycling', 'waste-to-energy models', 'microgrids', 'distributed energy resources (DER)', 'renewable energy integration', 'demand response', 'energy-as-a-service'],
                ],
            ],
        ],

        'manufacturing' => [
            'label'   => 'Modern Manufacturing',
            'pillars' => [
                [
                    'name'     => 'Advanced Production Systems (APS)',
                    'core_content_channels' => ['Industry 5.0', 'Human-Machine Collaboration'],
                    'focus'    => 'Evolution of production processes, additive manufacturing, and technology powering shop-floor performance.',
                    'readers'  => ['Chief Operating Officer (COO)', 'VP of Operations', 'Digital Transformation Director / Head of Industry 4.0', 'Sustainability (ESG) Manager', 'VP of Manufacturing Strategy', 'Supply Chain Director', 'Digital Twin Architect', 'Director of Advanced Production Systems', 'VP of Servitization / Business Model Innovation'],
                    'keywords' => ['additive manufacturing', 'production process innovation', 'lean manufacturing', 'advanced materials', '3D printing', 'agile manufacturing', 'mass customization', 'continuous flow production', 'nanomanufacturing'],
                ],
                [
                    'name'     => 'Supply Chain & Lifecycle Management',
                    'core_content_channels' => ['Industrial Sustainability & Circularity'],
                    'focus'    => 'End-to-end management of production assets and strategic optimization of supply chains.',
                    'readers'  => ['Chief Operating Officer (COO)', 'VP of Operations', 'Digital Transformation Director / Head of Industry 4.0', 'Sustainability (ESG) Manager', 'VP of Manufacturing Strategy', 'Supply Chain Director', 'Digital Twin Architect', 'Director of Advanced Production Systems', 'VP of Servitization / Business Model Innovation'],
                    'keywords' => ['end-to-end asset management', 'supply chain resilience', 'total cost of ownership', 'risk mitigation', 'nearshoring/reshoring', 'supplier collaboration', 'multi-tier supply chain visibility', 'sustainable sourcing'],
                ],
                [
                    'name'     => 'Smart Factory & Digitalization',
                    'core_content_channels' => ['Agentic AI & Industrial Intelligence'],
                    'focus'    => 'Digital core, data-driven manufacturing, and integration of automation into production workflows.',
                    'readers'  => ['Chief Operating Officer (COO)', 'VP of Operations', 'Digital Transformation Director / Head of Industry 4.0', 'Sustainability (ESG) Manager', 'VP of Manufacturing Strategy', 'Supply Chain Director', 'Digital Twin Architect', 'Director of Advanced Production Systems', 'VP of Servitization / Business Model Innovation'],
                    'keywords' => ['data connectivity', 'machine-to-machine (M2M)', 'predictive maintenance', 'AI/ML integration', 'digital thread', 'manufacturing execution systems (MES)', 'augmented reality (AR) in manufacturing', '5G factory networks', 'cloud computing'],
                ],
                [
                    'name'     => 'Servitization & Business Model Innovation',
                    'core_content_channels' => ['Servitization & XaaS Business Models'],
                    'focus'    => 'Service-based value creation, business model innovation, and growth strategies prioritizing long-term customer outcomes.',
                    'readers'  => ['Chief Operating Officer (COO)', 'VP of Operations', 'Digital Transformation Director / Head of Industry 4.0', 'Sustainability (ESG) Manager', 'VP of Manufacturing Strategy', 'Supply Chain Director', 'Digital Twin Architect', 'Director of Advanced Production Systems', 'VP of Servitization / Business Model Innovation'],
                    'keywords' => ['s-a-service business models', 'product-life extension', 'servitization strategy', 'ROI on sustainability', 'outcome-based business models', 'direct-to-user value', 'closed-loop feedback', 'continuous value delivery', 'carbon-neutral manufacturing'],
                ],
            ],
        ],
    ];

    /**
     * Get the full audience profile for a site slug.
     *
     * Delegates to TopLineCategoriesStore (CSV-seeded WP option) first.
     * Falls back to the hardcoded AUDIENCE constant if the option is empty.
     *
     * @param string $slug Site slug matching AllocationService::TARGET_SITES.
     * @return array|null Profile array with label, readers, and pillars, or null if not found.
     */
    public static function get_profile( string $slug ): ?array {
        // Check TopLineCategoriesStore first (CSV-seeded data — single source of truth)
        if ( class_exists( '\KH\Planner\Core\TopLineCategoriesStore' ) ) {
            $store = new \KH\Planner\Core\TopLineCategoriesStore();
            $category = $store->get_by_slug( $slug );
            if ( $category && ! empty( $category['pillars'] ) ) {
                // Convert category format to profile format
                $profile = [
                    'label'   => $category['name'] ?? ucfirst( $slug ),
                    'pillars' => $category['pillars'],
                ];
                // Merge in readers/keywords if the store has them (CSV import enriches these)
                if ( ! empty( $category['target_personas'] ) ) {
                    $profile['readers'] = $category['target_personas'];
                }
                if ( ! empty( $category['keywords'] ) ) {
                    $profile['keywords'] = $category['keywords'];
                }
                return $profile;
            }
        }

        // Fallback: hardcoded constant for fresh installs before CSV is imported
        return self::AUDIENCE[ $slug ] ?? null;
    }

    /**
     * Get a prompt-ready audience context string for injection into LLM system/user messages.
     *
     * Delegates to TopLineCategoriesStore enriched context (CSV-seeded) first.
     * Falls back to hardcoded AUDIENCE constant if the option is empty.
     *
     * @param string $slug Site slug matching AllocationService::TARGET_SITES.
     * @return string Human-readable context paragraph, or empty string if slug not found.
     */
    public static function get_audience_context( string $slug ): string {
        // Check TopLineCategoriesStore first (CSV-seeded data — single source of truth)
        if ( class_exists( '\KH\Planner\Core\TopLineCategoriesStore' ) ) {
            $store = new \KH\Planner\Core\TopLineCategoriesStore();
            $enriched = $store->get_enriched_audience_context( $slug );
            if ( ! empty( $enriched ) ) {
                return $enriched;
            }
        }

        // Fallback: use hardcoded profile
        $profile = self::get_profile( $slug );

        if ( ! $profile ) {
            return '';
        }

        $pillar_lines = [];
        $all_readers = [];
        $all_keywords = [];
        $all_channels = [];

        foreach ( $profile['pillars'] as $pillar ) {
            $pillar_lines[] = "{$pillar['name']} ({$pillar['focus']})";
            $all_readers = array_merge( $all_readers, $pillar['readers'] ?? [] );
            $all_keywords = array_merge( $all_keywords, $pillar['keywords'] ?? [] );

            if ( ! empty( $pillar['core_content_channels'] ) ) {
                $all_channels = array_merge( $all_channels, $pillar['core_content_channels'] );
            }
        }

        $unique_readers = implode( ', ', array_unique( $all_readers ) );
        $unique_keywords = implode( ', ', array_unique( $all_keywords ) );
        $unique_channels = implode( '; ', array_unique( $all_channels ) );
        $pillars_text = implode( '; ', $pillar_lines );

        $context = "This publication serves: {$unique_readers}. " .
               "Editorial coverage spans: {$pillars_text}. " .
               "Key topics include: {$unique_keywords}.";

        if ( ! empty( $unique_channels ) ) {
            $context .= " Core content channels include: {$unique_channels}.";
        }

        return $context;
    }

    /**
     * Get the human-readable label for a site slug.
     *
     * Delegates to TopLineCategoriesStore first, falls back to hardcoded constant.
     *
     * @param string $slug
     * @return string|null
     */
    public static function get_label( string $slug ): ?string {
        // Check TopLineCategoriesStore first
        if ( class_exists( '\KH\Planner\Core\TopLineCategoriesStore' ) ) {
            $store = new \KH\Planner\Core\TopLineCategoriesStore();
            $category = $store->get_by_slug( $slug );
            if ( $category && ! empty( $category['name'] ) ) {
                return $category['name'];
            }
        }

        // Fallback to hardcoded constant
        $profile = self::get_profile( $slug );
        return $profile ? $profile['label'] : null;
    }

    /**
     * Get the editorial pillars for a site slug.
     *
     * Delegates to TopLineCategoriesStore first, falls back to hardcoded constant.
     *
     * @param string $slug Site slug matching AllocationService::TARGET_SITES.
     * @return array Array of pillar arrays, each with 'name' and 'focus' keys.
     */
    public static function get_pillars( string $slug ): array {
        // Check TopLineCategoriesStore first
        if ( class_exists( '\KH\Planner\Core\TopLineCategoriesStore' ) ) {
            $store = new \KH\Planner\Core\TopLineCategoriesStore();
            $category = $store->get_by_slug( $slug );
            if ( $category && ! empty( $category['pillars'] ) ) {
                return $category['pillars'];
            }
        }

        // Fallback to hardcoded constant
        $profile = self::get_profile( $slug );
        return $profile ? $profile['pillars'] : [];
    }
}

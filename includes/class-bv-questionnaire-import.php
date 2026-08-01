<?php
/**
 * BusinessVance Questionnaire Import
 *
 * Imports pre-built questionnaire templates for Market Research and Business Plan services.
 * Data sourced from official BusinessVance questionnaire PDFs.
 *
 * @package BusinessVance_Services_Manager
 * @since   2.0.3
 * @updated 2.0.5 Refreshed all questions from official PDF questionnaires.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BV_Questionnaire_Import {

    /**
     * Import all questionnaire templates.
     *
     * Checks for existing templates by slug to prevent duplicates.
     * Returns import statistics.
     *
     * @since  2.0.3
     * @return array Import results per template.
     */
    public static function import_questionnaires() {
        global $wpdb;

        $results = array();

        $results['market_research'] = self::import_template( self::get_market_research_data() );
        $results['business_plan']   = self::import_template( self::get_business_plan_data() );

        return $results;
    }

    /**
     * Import a single questionnaire template.
     *
     * @since  2.0.3
     * @param  array $data Template definition (name, slug, description, sections).
     * @return array       Result with section and question counts.
     */
    private static function import_template( $data ) {
        global $wpdb;

        $table_templates = $wpdb->prefix . 'bv_questionnaire_templates';
        $table_sections  = $wpdb->prefix . 'bv_questionnaire_sections';
        $table_questions = $wpdb->prefix . 'bv_questionnaire_questions';

        // Check if already imported by slug.
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table_templates} WHERE slug = %s",
            $data['slug']
        ) );

        if ( $existing ) {
            return array(
                'status'   => 'skipped',
                'message'  => 'Template already exists: ' . $data['name'],
                'sections' => 0,
                'questions' => 0,
            );
        }

        // Insert template.
        $wpdb->insert( $table_templates, array(
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'description' => $data['description'],
            'version'     => '2.0',
            'status'      => 'published',
            'created_at'  => current_time( 'mysql' ),
            'updated_at'  => current_time( 'mysql' ),
        ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );

        $template_id = $wpdb->insert_id;
        $total_questions = 0;

        // Insert sections and questions.
        foreach ( $data['sections'] as $section ) {
            $wpdb->insert( $table_sections, array(
                'template_id'   => $template_id,
                'title'         => $section['title'],
                'description'   => $section['description'],
                'display_order' => $section['order'],
                'created_at'    => current_time( 'mysql' ),
            ), array( '%d', '%s', '%s', '%d', '%s' ) );

            $section_id = $wpdb->insert_id;

            foreach ( $section['questions'] as $order => $q ) {
                $options_json = ( ! empty( $q['options'] ) )
                    ? wp_json_encode( $q['options'] )
                    : '[]';

                $help = isset( $q['help_text'] ) ? $q['help_text'] : '';
                $placeholder = isset( $q['placeholder'] ) ? $q['placeholder'] : '';

                $wpdb->insert( $table_questions, array(
                    'section_id'    => $section_id,
                    'type'          => $q['type'],
                    'label'         => $q['label'],
                    'placeholder'   => $placeholder,
                    'is_required'   => ! empty( $q['required'] ) ? 1 : 0,
                    'options'       => $options_json,
                    'help_text'     => $help,
                    'display_order' => $order + 1,
                ), array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d' ) );

                $total_questions++;
            }
        }

        return array(
            'status'    => 'imported',
            'message'   => 'Imported: ' . $data['name'],
            'sections'  => count( $data['sections'] ),
            'questions' => $total_questions,
        );
    }

    /* ==========================================================================
     * MARKET RESEARCH REPORT QUESTIONNAIRE
     * Source: BusinessVance_Market_Research_Report_Questionnaire_260714_110737.pdf
     * 14 pages, 20 sections
     * ========================================================================== */

    private static function get_market_research_data() {
        return array(
            'name'        => 'Market Research Report Questionnaire',
            'slug'        => 'market-research-report',
            'description' => 'Client questionnaire for preparing a market research report. Please answer as completely and accurately as possible. The report will be based on the information supplied, supporting documents and available market research.',
            'sections'    => array(

                // ── S1: Client Information (PDF p.1) ──
                array(
                    'title'       => 'Client Information',
                    'description' => 'Basic client and contact information.',
                    'order'       => 1,
                    'questions'   => array(
                        array( 'type' => 'text', 'label' => 'Full name', 'required' => true ),
                        array( 'type' => 'text', 'label' => 'Business name', 'required' => true ),
                        array( 'type' => 'email', 'label' => 'Email address', 'required' => true ),
                        array( 'type' => 'phone', 'label' => 'Contact number', 'required' => true ),
                        array( 'type' => 'text', 'label' => 'City / Province / Country' ),
                        array( 'type' => 'select', 'label' => 'Preferred report language', 'options' => array(
                            array( 'value' => 'english', 'label' => 'English' ),
                            array( 'value' => 'afrikaans', 'label' => 'Afrikaans' ),
                        ) ),
                        array( 'type' => 'date', 'label' => 'Preferred delivery date' ),
                    ),
                ),

                // ── S2: Business Overview (PDF p.1) ──
                array(
                    'title'       => 'Business Overview',
                    'description' => 'Overview of the business or business idea.',
                    'order'       => 2,
                    'questions'   => array(
                        array( 'type' => 'textarea', 'label' => 'Briefly describe the business or business idea', 'required' => true ),
                        array( 'type' => 'textarea', 'label' => 'What products or services do you offer or plan to offer?', 'required' => true ),
                        array( 'type' => 'textarea', 'label' => 'What problem does your business solve?' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'What stage is the business currently in?',
                            'options' => array( 'Idea stage', 'Research stage', 'Planning stage', 'Pre-launch', 'Already operating', 'Expanding', 'Launching a new product or service' ),
                        ),
                        array( 'type' => 'text', 'label' => 'When did the business start, or when do you plan to launch?' ),
                    ),
                ),

                // ── S3: Purpose of the Market Research (PDF p.2) ──
                array(
                    'title'       => 'Purpose of the Market Research',
                    'description' => 'What you want the research to help you decide.',
                    'order'       => 3,
                    'questions'   => array(
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'What do you want the market research report to help you decide?',
                            'options' => array(
                                'Whether there is sufficient demand',
                                'Who the target customer should be',
                                'What prices customers may accept',
                                'Which area or location to target',
                                'How large the market may be',
                                'What customers want',
                                'Which marketing channels to use',
                                'How to position the business',
                                'Whether to launch a new product or service',
                                'Whether to expand into a new market',
                            ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'What are the main questions you want answered?' ),
                        array( 'type' => 'radio', 'label' => 'Is there a particular decision deadline?', 'options' => array( 'Yes', 'No' ) ),
                        array( 'type' => 'date', 'label' => 'If yes, what is the deadline date?', 'help_text' => 'Only complete if you selected Yes above.' ),
                    ),
                ),

                // ── S4: Products and Services (PDF p.3) ──
                array(
                    'title'       => 'Products and Services',
                    'description' => 'Products or services to be researched.',
                    'order'       => 4,
                    'questions'   => array(
                        array( 'type' => 'paragraph', 'label' => 'List the products or services to be researched. Describe each one with its priority and price.' ),
                        array( 'type' => 'textarea', 'label' => 'Products or services to be researched', 'placeholder' => "Product/Service | Description | Priority | Price\nList each item..." ),
                        array( 'type' => 'text', 'label' => 'Which product or service is the main focus of the research?' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'How would you describe the offering?',
                            'options' => array( 'New to the market', 'Already available from competitors', 'Improved version of an existing product', 'Specialist service', 'Luxury product or service', 'Low-cost option' ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'What makes the product or service different?' ),
                        array(
                            'type'    => 'radio',
                            'label'   => 'How often will customers purchase?',
                            'options' => array( 'Once-off purchase', 'Frequent repeat purchase', 'Occasional repeat purchase', 'Monthly subscription', 'Annual subscription', 'Contract-based', 'Unsure' ),
                        ),
                    ),
                ),

                // ── S5: Geographic Market (PDF p.3) ──
                array(
                    'title'       => 'Geographic Market',
                    'description' => 'Geographic area to focus on.',
                    'order'       => 5,
                    'questions'   => array(
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Which market should the report focus on?',
                            'options' => array( 'Specific suburb', 'Specific town or city', 'District or municipality', 'Province', 'South Africa', 'Southern Africa', 'International market', 'Online market' ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'List the main locations to be researched' ),
                        array( 'type' => 'radio', 'label' => 'Will customers visit a physical location?', 'options' => array( 'Yes', 'No', 'Both physical and online' ) ),
                        array( 'type' => 'radio', 'label' => 'How far are customers likely to travel?', 'options' => array( 'Less than 5 km', '5-10 km', '10-25 km', 'More than 25 km', 'Not applicable', 'Unsure' ) ),
                        array( 'type' => 'textarea', 'label' => 'Are there specific areas you want excluded from the research?' ),
                    ),
                ),

                // ── S6: Target Customer (PDF p.4) ──
                array(
                    'title'       => 'Target Customer',
                    'description' => 'Who your ideal customer is.',
                    'order'       => 6,
                    'questions'   => array(
                        array( 'type' => 'textarea', 'label' => 'Who do you believe your ideal customer is?' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Is the business aimed mainly at?',
                            'options' => array( 'Individual consumers', 'Families', 'Parents', 'Children or teenagers', 'Students', 'Professionals', 'Small businesses', 'Large businesses', 'Government', 'Schools or educational institutions', 'Non-profit organisations' ),
                        ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'What is the typical customer age range?',
                            'options' => array( 'Under 18', '18-24', '25-34', '35-44', '45-54', '55-64', '65+', 'Not age-specific' ),
                        ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Which customer characteristics are most important?',
                            'options' => array( 'Income', 'Occupation', 'Location', 'Education level', 'Family status', 'Lifestyle', 'Business size', 'Industry', 'Buying behaviour' ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'What income level or budget is typical for your ideal customer?' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Who normally makes the buying decision?',
                            'options' => array( 'User of the product', 'Parent or guardian', 'Business owner', 'Manager', 'Procurement department', 'School or institution' ),
                        ),
                    ),
                ),

                // ── S7: Customer Needs and Problems (PDF p.5) ──
                array(
                    'title'       => 'Customer Needs and Problems',
                    'description' => 'What problems customers experience and how they currently solve them.',
                    'order'       => 7,
                    'questions'   => array(
                        array( 'type' => 'textarea', 'label' => 'What main problem does the customer experience?' ),
                        array( 'type' => 'radio', 'label' => 'How serious or urgent is this problem?', 'options' => array( 'Low importance', 'Moderate importance', 'High importance', 'Urgent' ) ),
                        array( 'type' => 'textarea', 'label' => 'How are customers currently solving the problem?' ),
                        array( 'type' => 'textarea', 'label' => 'What do customers dislike about current solutions?' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'What benefits are customers likely to value most?',
                            'options' => array( 'Lower price', 'Better quality', 'Faster service', 'Convenience', 'Reliability', 'Personal service', 'Specialist knowledge', 'Location', 'Flexibility', 'Better results' ),
                        ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'What might prevent customers from buying?',
                            'options' => array( 'Price', 'Lack of trust', 'Low awareness', 'Competition', 'Distance', 'Lack of urgency', 'Existing contracts', 'Preference for familiar brands' ),
                        ),
                    ),
                ),

                // ── S8: Customer Buying Behaviour (PDF p.5-6) ──
                array(
                    'title'       => 'Customer Buying Behaviour',
                    'description' => 'How customers search for and purchase products or services.',
                    'order'       => 8,
                    'questions'   => array(
                        array( 'type' => 'radio', 'label' => 'How often are customers likely to buy?', 'options' => array( 'Daily', 'Weekly', 'Monthly', 'A few times per year', 'Once-off', 'Contract basis', 'Unsure' ) ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Where do customers currently buy similar products or services?',
                            'options' => array( 'Physical stores', 'Online shops', 'Social media', 'Directly from suppliers', 'Through referrals', 'At markets or events', 'Through agents' ),
                        ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'How do customers normally search for this product or service?',
                            'options' => array( 'Google', 'Facebook', 'Instagram', 'TikTok', 'LinkedIn', 'WhatsApp', 'Word of mouth', 'Business directories', 'In-store visits' ),
                        ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'What factors are most likely to influence the purchase?',
                            'options' => array( 'Price', 'Quality', 'Reviews', 'Recommendations', 'Brand reputation', 'Convenience', 'Speed', 'Customer service', 'Guarantees', 'Qualifications or experience' ),
                        ),
                        array( 'type' => 'radio', 'label' => 'Is the purchase usually planned or urgent?', 'options' => array( 'Planned well in advance', 'Short-notice purchase', 'Emergency purchase', 'Impulse purchase', 'Depends on the customer' ) ),
                    ),
                ),

                // ── S9: Demand Validation (PDF p.6-7) ──
                array(
                    'title'       => 'Demand Validation',
                    'description' => 'Evidence that customers want the product or service.',
                    'order'       => 9,
                    'questions'   => array(
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'What evidence do you currently have that there is demand?',
                            'options' => array( 'Customer enquiries', 'Existing sales', 'Pre-orders', 'Survey results', 'Social media engagement', 'Website traffic', 'Waiting list', 'Competitor activity', 'Industry experience', 'No evidence yet' ),
                        ),
                        array( 'type' => 'text', 'label' => 'Approximately how many potential customers have shown interest?' ),
                        array( 'type' => 'radio', 'label' => 'Have any customers already paid or committed to buy?', 'options' => array( 'Yes', 'No' ) ),
                        array( 'type' => 'textarea', 'label' => 'If yes, provide details', 'help_text' => 'Describe customer commitments.' ),
                        array( 'type' => 'radio', 'label' => 'Have you tested the idea with a trial, sample, pilot or minimum product?', 'options' => array( 'Yes', 'No' ) ),
                        array( 'type' => 'textarea', 'label' => 'If yes, describe results' ),
                        array( 'type' => 'radio', 'label' => 'Have you conducted customer surveys or interviews?', 'options' => array( 'Yes', 'No' ) ),
                        array( 'type' => 'textarea', 'label' => 'If yes, attach or summarise findings' ),
                    ),
                ),

                // ── S10: Market Size (PDF p.7) ──
                array(
                    'title'       => 'Market Size',
                    'description' => 'Estimating the size of the target market.',
                    'order'       => 10,
                    'questions'   => array(
                        array( 'type' => 'text', 'label' => 'Approximately how many potential customers do you believe are in the target market?' ),
                        array( 'type' => 'textarea', 'label' => 'How did you estimate this number?' ),
                        array( 'type' => 'radio', 'label' => 'Is the target market?', 'options' => array( 'Growing', 'Stable', 'Declining', 'Unknown' ) ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Are you targeting?',
                            'options' => array( 'Broad market', 'Specialised niche', 'Premium market', 'Budget market', 'Mass market', 'Unsure' ),
                        ),
                        array( 'type' => 'text', 'label' => 'What percentage of the market would you realistically like to reach in the first year?' ),
                    ),
                ),

                // ── S11: Industry Information (PDF p.7-8) ──
                array(
                    'title'       => 'Industry Information',
                    'description' => 'Industry the business operates in.',
                    'order'       => 11,
                    'questions'   => array(
                        array( 'type' => 'text', 'label' => 'What industry does the business operate in?' ),
                        array( 'type' => 'radio', 'label' => 'Do you have experience in this industry?', 'options' => array( 'Yes', 'No' ) ),
                        array( 'type' => 'textarea', 'label' => 'If yes, describe your experience' ),
                        array( 'type' => 'textarea', 'label' => 'What important changes are taking place in the industry?' ),
                        array( 'type' => 'radio', 'label' => 'Are new technologies affecting the industry?', 'options' => array( 'Yes', 'No', 'Unsure' ) ),
                        array( 'type' => 'textarea', 'label' => 'If yes, explain how technology is affecting the industry' ),
                        array( 'type' => 'textarea', 'label' => 'List any industry associations, professional bodies or important organisations' ),
                        array( 'type' => 'textarea', 'label' => 'List any licences, regulations or professional requirements' ),
                    ),
                ),

                // ── S12: Market Trends (PDF p.8) ──
                array(
                    'title'       => 'Market Trends',
                    'description' => 'Trends that could affect demand.',
                    'order'       => 12,
                    'questions'   => array(
                        array( 'type' => 'textarea', 'label' => 'Which trends could increase demand?' ),
                        array( 'type' => 'textarea', 'label' => 'Which trends could reduce demand?' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Is demand influenced by?',
                            'options' => array( 'The economy', 'Interest rates', 'Technology', 'Population growth', 'Lifestyle changes', 'Education trends', 'Government policy', 'Environmental concerns', 'Social media trends' ),
                        ),
                        array( 'type' => 'radio', 'label' => 'Is the market affected by seasonal demand?', 'options' => array( 'Yes', 'No', 'Unsure' ) ),
                        array( 'type' => 'textarea', 'label' => 'If yes, list busiest periods' ),
                        array( 'type' => 'textarea', 'label' => 'Are there future changes that could affect the market?' ),
                    ),
                ),

                // ── S13: Competitors and Alternatives (PDF p.9) ──
                array(
                    'title'       => 'Competitors and Alternatives',
                    'description' => 'Known competitors and market alternatives.',
                    'order'       => 13,
                    'questions'   => array(
                        array( 'type' => 'paragraph', 'label' => 'List the main competitors you already know about. Include their location, main offering, and price range.' ),
                        array( 'type' => 'textarea', 'label' => 'List the main competitors', 'placeholder' => "Competitor | Location/Website | Main Offering | Price Range\nList each competitor..." ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Competitors are mainly?',
                            'options' => array( 'Local businesses', 'National businesses', 'International businesses', 'Online businesses', 'Informal businesses', 'A combination' ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'What do competitors do well?' ),
                        array( 'type' => 'textarea', 'label' => 'What weaknesses or gaps have you noticed?' ),
                        array( 'type' => 'textarea', 'label' => 'What alternatives could customers use instead?' ),
                        array( 'type' => 'radio', 'label' => 'How competitive is the market?', 'options' => array( 'Low competition', 'Moderate competition', 'High competition', 'Unsure' ) ),
                    ),
                ),

                // ── S14: Pricing Research (PDF p.9-10) ──
                array(
                    'title'       => 'Pricing Research',
                    'description' => 'Pricing information and strategy.',
                    'order'       => 14,
                    'questions'   => array(
                        array( 'type' => 'text', 'label' => 'Current or planned price' ),
                        array( 'type' => 'text', 'label' => 'Lowest profitable price' ),
                        array( 'type' => 'text', 'label' => 'Highest price customers may accept' ),
                        array( 'type' => 'textarea', 'label' => 'What prices do competitors charge?' ),
                        array( 'type' => 'radio', 'label' => 'Which pricing position do you want?', 'options' => array( 'Lowest-cost option', 'Affordable value option', 'Similar to competitors', 'Premium option', 'Specialist high-value option', 'Unsure' ) ),
                        array( 'type' => 'radio', 'label' => 'Include pricing recommendations?', 'options' => array( 'Yes', 'No' ) ),
                        array( 'type' => 'radio', 'label' => 'Are discounts, bundles or payment plans being considered?', 'options' => array( 'Yes', 'No' ) ),
                        array( 'type' => 'textarea', 'label' => 'If yes, provide details' ),
                    ),
                ),

                // ── S15: Sales Channels (PDF p.10) ──
                array(
                    'title'       => 'Sales Channels',
                    'description' => 'Where and how customers will purchase.',
                    'order'       => 15,
                    'questions'   => array(
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Where will customers purchase?',
                            'options' => array( 'Physical location', 'Website', 'Online store', 'WhatsApp', 'Social media', 'Sales representative', 'Retail partners', 'Markets or events', 'Customer premises' ),
                        ),
                        array( 'type' => 'text', 'label' => 'Which sales channel do you expect to generate the most business?' ),
                        array( 'type' => 'radio', 'label' => 'Do you already have access to distribution or sales channels?', 'options' => array( 'Yes', 'No' ) ),
                        array( 'type' => 'textarea', 'label' => 'If yes, explain' ),
                        array( 'type' => 'radio', 'label' => 'Will delivery or transport be required?', 'options' => array( 'Yes', 'No' ) ),
                        array( 'type' => 'textarea', 'label' => 'If yes, describe delivery or transport requirements' ),
                    ),
                ),

                // ── S16: Marketing Channels (PDF p.10-11) ──
                array(
                    'title'       => 'Marketing Channels',
                    'description' => 'Marketing platforms and strategies.',
                    'order'       => 16,
                    'questions'   => array(
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Which marketing platforms do you use or plan to use?',
                            'options' => array( 'Facebook', 'Instagram', 'TikTok', 'LinkedIn', 'YouTube', 'Google Ads', 'Website', 'Email', 'WhatsApp', 'Flyers', 'Local newspapers', 'Radio', 'Events', 'Referrals', 'Partnerships' ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'Which platforms does your target customer use most?' ),
                        array( 'type' => 'text', 'label' => 'Planned monthly marketing budget' ),
                        array( 'type' => 'textarea', 'label' => 'Current audience or customer database', 'placeholder' => "Platform | Followers/Contacts | Engagement/Notes | Date" ),
                        array( 'type' => 'textarea', 'label' => 'Which marketing activities have worked well in the past?' ),
                        array( 'type' => 'textarea', 'label' => 'Which marketing activities have not worked?' ),
                    ),
                ),

                // ── S17: Current Business Performance (PDF p.11-12) ──
                array(
                    'title'       => 'Current Business Performance',
                    'description' => 'Complete only if the business is already operating.',
                    'order'       => 17,
                    'questions'   => array(
                        array( 'type' => 'paragraph', 'label' => 'Complete this section only if the business is already operating.' ),
                        array( 'type' => 'text', 'label' => 'Average customers per month' ),
                        array( 'type' => 'text', 'label' => 'Average monthly sales revenue' ),
                        array( 'type' => 'text', 'label' => 'Average sale per customer' ),
                        array( 'type' => 'text', 'label' => 'Best-selling product or service' ),
                        array( 'type' => 'text', 'label' => 'Slowest-selling product or service' ),
                        array( 'type' => 'text', 'label' => 'Strongest months' ),
                        array( 'type' => 'text', 'label' => 'Weakest months' ),
                        array( 'type' => 'text', 'label' => 'Estimated repeat-customer percentage' ),
                        array( 'type' => 'textarea', 'label' => 'Where do most current customers come from?' ),
                    ),
                ),

                // ── S18: Research Sources (PDF p.12) ──
                array(
                    'title'       => 'Research Sources',
                    'description' => 'Information and documents available.',
                    'order'       => 18,
                    'questions'   => array(
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Please indicate any information or documents already available',
                            'options' => array( 'Customer survey results', 'Sales records', 'Customer database', 'Social media statistics', 'Website statistics', 'Industry reports', 'Supplier information', 'Competitor price lists', 'Product photographs', 'Customer reviews', 'Previous research' ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'Relevant website or social media links', 'placeholder' => 'Paste any relevant URLs...' ),
                    ),
                ),

                // ── S19: Research Focus (PDF p.13) ──
                array(
                    'title'       => 'Research Focus',
                    'description' => 'Rate the importance of each research area.',
                    'order'       => 19,
                    'questions'   => array(
                        array( 'type' => 'paragraph', 'label' => 'Rate the importance of each area from 1 to 5, where 5 is most important.' ),
                        array( 'type' => 'select', 'label' => 'Market demand', 'options' => array(
                            array( 'value' => '1', 'label' => '1 - Low importance' ),
                            array( 'value' => '2', 'label' => '2' ),
                            array( 'value' => '3', 'label' => '3 - Medium importance' ),
                            array( 'value' => '4', 'label' => '4' ),
                            array( 'value' => '5', 'label' => '5 - High importance' ),
                        ) ),
                        array( 'type' => 'select', 'label' => 'Market size', 'options' => array(
                            array( 'value' => '1', 'label' => '1 - Low importance' ),
                            array( 'value' => '2', 'label' => '2' ),
                            array( 'value' => '3', 'label' => '3 - Medium importance' ),
                            array( 'value' => '4', 'label' => '4' ),
                            array( 'value' => '5', 'label' => '5 - High importance' ),
                        ) ),
                        array( 'type' => 'select', 'label' => 'Customer profile', 'options' => array(
                            array( 'value' => '1', 'label' => '1 - Low importance' ),
                            array( 'value' => '2', 'label' => '2' ),
                            array( 'value' => '3', 'label' => '3 - Medium importance' ),
                            array( 'value' => '4', 'label' => '4' ),
                            array( 'value' => '5', 'label' => '5 - High importance' ),
                        ) ),
                        array( 'type' => 'select', 'label' => 'Customer needs', 'options' => array(
                            array( 'value' => '1', 'label' => '1 - Low importance' ),
                            array( 'value' => '2', 'label' => '2' ),
                            array( 'value' => '3', 'label' => '3 - Medium importance' ),
                            array( 'value' => '4', 'label' => '4' ),
                            array( 'value' => '5', 'label' => '5 - High importance' ),
                        ) ),
                        array( 'type' => 'select', 'label' => 'Buying behaviour', 'options' => array(
                            array( 'value' => '1', 'label' => '1 - Low importance' ),
                            array( 'value' => '2', 'label' => '2' ),
                            array( 'value' => '3', 'label' => '3 - Medium importance' ),
                            array( 'value' => '4', 'label' => '4' ),
                            array( 'value' => '5', 'label' => '5 - High importance' ),
                        ) ),
                        array( 'type' => 'select', 'label' => 'Pricing', 'options' => array(
                            array( 'value' => '1', 'label' => '1 - Low importance' ),
                            array( 'value' => '2', 'label' => '2' ),
                            array( 'value' => '3', 'label' => '3 - Medium importance' ),
                            array( 'value' => '4', 'label' => '4' ),
                            array( 'value' => '5', 'label' => '5 - High importance' ),
                        ) ),
                        array( 'type' => 'select', 'label' => 'Competitors', 'options' => array(
                            array( 'value' => '1', 'label' => '1 - Low importance' ),
                            array( 'value' => '2', 'label' => '2' ),
                            array( 'value' => '3', 'label' => '3 - Medium importance' ),
                            array( 'value' => '4', 'label' => '4' ),
                            array( 'value' => '5', 'label' => '5 - High importance' ),
                        ) ),
                        array( 'type' => 'select', 'label' => 'Industry trends', 'options' => array(
                            array( 'value' => '1', 'label' => '1 - Low importance' ),
                            array( 'value' => '2', 'label' => '2' ),
                            array( 'value' => '3', 'label' => '3 - Medium importance' ),
                            array( 'value' => '4', 'label' => '4' ),
                            array( 'value' => '5', 'label' => '5 - High importance' ),
                        ) ),
                        array( 'type' => 'select', 'label' => 'Best location', 'options' => array(
                            array( 'value' => '1', 'label' => '1 - Low importance' ),
                            array( 'value' => '2', 'label' => '2' ),
                            array( 'value' => '3', 'label' => '3 - Medium importance' ),
                            array( 'value' => '4', 'label' => '4' ),
                            array( 'value' => '5', 'label' => '5 - High importance' ),
                        ) ),
                        array( 'type' => 'select', 'label' => 'Marketing channels', 'options' => array(
                            array( 'value' => '1', 'label' => '1 - Low importance' ),
                            array( 'value' => '2', 'label' => '2' ),
                            array( 'value' => '3', 'label' => '3 - Medium importance' ),
                            array( 'value' => '4', 'label' => '4' ),
                            array( 'value' => '5', 'label' => '5 - High importance' ),
                        ) ),
                        array( 'type' => 'select', 'label' => 'Growth opportunities', 'options' => array(
                            array( 'value' => '1', 'label' => '1 - Low importance' ),
                            array( 'value' => '2', 'label' => '2' ),
                            array( 'value' => '3', 'label' => '3 - Medium importance' ),
                            array( 'value' => '4', 'label' => '4' ),
                            array( 'value' => '5', 'label' => '5 - High importance' ),
                        ) ),
                    ),
                ),

                // ── S20: Expected Outcome (PDF p.13) ──
                array(
                    'title'       => 'Expected Outcome',
                    'description' => 'What would make the report valuable.',
                    'order'       => 20,
                    'questions'   => array(
                        array( 'type' => 'textarea', 'label' => 'What would make the market attractive enough for you to proceed?' ),
                        array( 'type' => 'textarea', 'label' => 'What findings would make you reconsider the idea?' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'What would you like the report to recommend?',
                            'options' => array( 'Proceed as planned', 'Proceed with changes', 'Test the market first', 'Change the target customer', 'Adjust pricing', 'Change the location', 'Improve the offering', 'Do not proceed' ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'Is there any other information we should consider?' ),
                    ),
                ),

            ),
        );
    }

    /* ==========================================================================
     * BUSINESS PLAN QUESTIONNAIRE
     * Source: BusinessVance_Business_Plan_Questionnaire_FILLABLE (1) (1).pdf
     * 19 pages, 24 sections
     * ========================================================================== */

    private static function get_business_plan_data() {
        return array(
            'name'        => 'Business Plan Questionnaire',
            'slug'        => 'business-plan',
            'description' => 'Client questionnaire for preparing a professional business plan covering concept, market, operations, marketing, management, finances, risks and growth strategy.',
            'sections'    => array(

                // ── S1: Client Information (PDF p.1) ──
                array(
                    'title'       => 'Client Information',
                    'description' => 'Basic client and contact information.',
                    'order'       => 1,
                    'questions'   => array(
                        array( 'type' => 'text', 'label' => 'Full name', 'required' => true ),
                        array( 'type' => 'text', 'label' => 'Business name', 'required' => true ),
                        array( 'type' => 'email', 'label' => 'Email address', 'required' => true ),
                        array( 'type' => 'phone', 'label' => 'Contact number', 'required' => true ),
                        array( 'type' => 'text', 'label' => 'City / Province / Country' ),
                        array( 'type' => 'select', 'label' => 'Preferred report language', 'options' => array(
                            array( 'value' => 'english', 'label' => 'English' ),
                            array( 'value' => 'afrikaans', 'label' => 'Afrikaans' ),
                        ) ),
                    ),
                ),

                // ── S2: Purpose of the Business Plan (PDF p.1) ──
                array(
                    'title'       => 'Purpose of the Business Plan',
                    'description' => 'Why the business plan is being prepared.',
                    'order'       => 2,
                    'questions'   => array(
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Purpose of the business plan',
                            'options' => array( 'Start a new business', 'Apply for bank funding', 'Apply for government funding', 'Attract an investor', 'Approach a business partner', 'Expand an existing business', 'Purchase equipment or assets', 'Apply for a lease', 'Guide operations', 'Introduce a new product or service', 'Purchase an existing business' ),
                        ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Who is the primary audience?',
                            'options' => array( 'Business owner', 'Bank or lender', 'Investor', 'Funding organisation', 'Government department', 'Business partner', 'Landlord', 'Management team' ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'What are the main questions the business plan should answer?' ),
                        array( 'type' => 'radio', 'label' => 'Is there a funding, application or presentation deadline?', 'options' => array( 'Yes', 'No' ) ),
                        array( 'type' => 'select', 'label' => 'Forecast period', 'options' => array(
                            array( 'value' => '1-year', 'label' => 'One-year plan' ),
                            array( 'value' => '3-year', 'label' => 'Three-year plan' ),
                            array( 'value' => '5-year', 'label' => 'Five-year plan' ),
                        ) ),
                    ),
                ),

                // ── S3: Business Overview (PDF p.2) ──
                array(
                    'title'       => 'Business Overview',
                    'description' => 'Overview of the business or business idea.',
                    'order'       => 3,
                    'questions'   => array(
                        array( 'type' => 'textarea', 'label' => 'Briefly describe the business or business idea', 'required' => true ),
                        array( 'type' => 'textarea', 'label' => 'What products or services will the business offer?', 'required' => true ),
                        array( 'type' => 'textarea', 'label' => 'What problem does the business solve?' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Business stage',
                            'options' => array( 'Idea stage', 'Research stage', 'Planning stage', 'Pre-launch', 'Recently launched', 'Operating under 1 year', 'Operating 1-3 years', 'Operating over 3 years', 'Expanding', 'Purchasing an existing business' ),
                        ),
                        array( 'type' => 'text', 'label' => 'Start date or planned launch date' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Business type',
                            'options' => array( 'Product-based', 'Service-based', 'Subscription-based', 'Contract-based', 'Commission-based', 'Rental-based', 'Manufacturing', 'Retail', 'Wholesale', 'Online', 'Physical', 'Mobile', 'Combination' ),
                        ),
                    ),
                ),

                // ── S4: Business Name and Legal Structure (PDF p.2) ──
                array(
                    'title'       => 'Business Name and Legal Structure',
                    'description' => 'Business name, registration and legal structure.',
                    'order'       => 4,
                    'questions'   => array(
                        array( 'type' => 'text', 'label' => 'Proposed or registered business name', 'required' => true ),
                        array( 'type' => 'text', 'label' => 'Registration number, if applicable' ),
                        array( 'type' => 'radio', 'label' => 'Name registration status', 'options' => array( 'Name approved/registered', 'Application in progress', 'Not yet registered' ) ),
                        array( 'type' => 'radio', 'label' => 'Legal structure', 'options' => array( 'Sole proprietor', 'Private company', 'Partnership', 'Cooperative', 'Non-profit organisation', 'Franchise', 'Not yet decided' ) ),
                        array( 'type' => 'textarea', 'label' => 'Why was this structure selected?' ),
                        array( 'type' => 'radio', 'label' => 'Tax registration status', 'options' => array( 'Tax registered', 'Tax registration planned', 'VAT registered', 'VAT registration planned', 'Not registered', 'Unsure' ) ),
                        array( 'type' => 'textarea', 'label' => 'Licences, permits or professional registrations required' ),
                    ),
                ),

                // ── S5: Ownership and Shareholding (PDF p.3) ──
                array(
                    'title'       => 'Ownership and Shareholding',
                    'description' => 'Business owners and their ownership percentages.',
                    'order'       => 5,
                    'questions'   => array(
                        array( 'type' => 'paragraph', 'label' => 'List all owners, their positions, ownership percentages, and contributions.' ),
                        array( 'type' => 'textarea', 'label' => 'Ownership details', 'placeholder' => "Name | Position | Ownership % | Contribution\nList each owner...", 'required' => true ),
                        array( 'type' => 'radio', 'label' => 'Is there a shareholders/partnership agreement?', 'options' => array( 'Shareholders/partnership agreement in place', 'No agreement', 'Not applicable', 'Investors may receive ownership' ) ),
                        array( 'type' => 'text', 'label' => 'Who will make final business decisions?' ),
                    ),
                ),

                // ── S6: Vision, Mission and Objectives (PDF p.3) ──
                array(
                    'title'       => 'Vision, Mission and Objectives',
                    'description' => 'Business vision, mission and goals.',
                    'order'       => 6,
                    'questions'   => array(
                        array( 'type' => 'textarea', 'label' => 'Long-term vision' ),
                        array( 'type' => 'textarea', 'label' => 'Mission or main purpose' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Main 12-month objectives',
                            'options' => array( 'Launch business', 'Reach break-even', 'Gain target number of customers', 'Achieve sales target', 'Employ staff', 'Secure contracts', 'Build brand awareness', 'Expand product range', 'Open premises' ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'Describe your main objectives for the next 12 months' ),
                        array( 'type' => 'textarea', 'label' => 'Three-year goals' ),
                        array( 'type' => 'textarea', 'label' => 'What would success look like after five years?' ),
                    ),
                ),

                // ── S7: Products and Services (PDF p.3-4) ──
                array(
                    'title'       => 'Products and Services',
                    'description' => 'Detailed description of products or services.',
                    'order'       => 7,
                    'questions'   => array(
                        array( 'type' => 'paragraph', 'label' => 'List all products or services with descriptions, selling prices, and estimated costs.' ),
                        array( 'type' => 'textarea', 'label' => 'Products and services list', 'placeholder' => "Product/Service | Description | Selling Price | Est. Cost\nList each item..." ),
                        array( 'type' => 'text', 'label' => 'Main product or service focus' ),
                        array( 'type' => 'text', 'label' => 'Most profitable product or service' ),
                        array( 'type' => 'textarea', 'label' => 'New products or services planned' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Source of products',
                            'options' => array( 'Manufacture internally', 'Purchase from suppliers', 'Import', 'Third-party manufacture', 'Resell finished products', 'Not applicable' ),
                        ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Purchase frequency',
                            'options' => array( 'Once-off purchase', 'Weekly repeat', 'Monthly repeat', 'Occasional repeat', 'Subscription', 'Contract basis', 'Seasonal' ),
                        ),
                    ),
                ),

                // ── S8: Unique Value Proposition (PDF p.4) ──
                array(
                    'title'       => 'Unique Value Proposition',
                    'description' => 'What makes the business unique and competitive.',
                    'order'       => 8,
                    'questions'   => array(
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Why should customers choose your business?',
                            'options' => array( 'Lower prices', 'Better quality', 'Faster service', 'Better customer service', 'Specialist expertise', 'Unique offering', 'Greater convenience', 'Better location', 'Customisation', 'More experience', 'Better guarantees' ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'Strongest customer benefit' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Evidence supporting your claims',
                            'options' => array( 'Qualifications', 'Industry experience', 'Testimonials', 'Reviews', 'Case studies', 'Product testing', 'Guarantees', 'Awards', 'Existing sales' ),
                        ),
                    ),
                ),

                // ── S9: Industry Overview and Market Opportunity (PDF p.5) ──
                array(
                    'title'       => 'Industry Overview and Market Opportunity',
                    'description' => 'Industry the business operates in and opportunity.',
                    'order'       => 9,
                    'questions'   => array(
                        array( 'type' => 'text', 'label' => 'Industry', 'required' => true ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Industry status',
                            'options' => array( 'Growing', 'Stable', 'Declining', 'Highly competitive', 'New/emerging', 'Seasonal', 'Unsure' ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'Industry trends, technology changes and regulations' ),
                        array( 'type' => 'textarea', 'label' => 'Why is there an opportunity for this business?' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Evidence of demand',
                            'options' => array( 'Existing sales', 'Customer enquiries', 'Pre-orders', 'Waiting list', 'Customer surveys', 'Social-media interest', 'Competitor activity', 'Industry research', 'Previous experience', 'Signed contracts', 'No evidence yet' ),
                        ),
                        array( 'type' => 'text', 'label' => 'Number of interested or committed customers' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Demand type',
                            'options' => array( 'Consistent demand', 'Seasonal demand', 'Event-based', 'Contract-based', 'Uncertain' ),
                        ),
                    ),
                ),

                // ── S10: Target Market and Buying Behaviour (PDF p.5-6) ──
                array(
                    'title'       => 'Target Market and Buying Behaviour',
                    'description' => 'Ideal customer profile and buying patterns.',
                    'order'       => 10,
                    'questions'   => array(
                        array( 'type' => 'textarea', 'label' => 'Describe the ideal customer' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Target customer types',
                            'options' => array( 'Individuals', 'Families', 'Parents', 'Children/teenagers', 'Students', 'Professionals', 'Small businesses', 'Large businesses', 'Government', 'Schools/institutions', 'Non-profit organisations' ),
                        ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Age range',
                            'options' => array( 'Under 18', '18-24', '25-34', '35-44', '45-54', '55-64', '65+', 'Not age-specific' ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'Typical income, budget or business size' ),
                        array( 'type' => 'textarea', 'label' => 'Geographic areas to target' ),
                        array( 'type' => 'textarea', 'label' => 'Customer problem or need' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Most valued factors',
                            'options' => array( 'Price', 'Quality', 'Convenience', 'Speed', 'Reliability', 'Customer service', 'Expertise', 'Reputation', 'Flexibility', 'Results' ),
                        ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'How do customers search?',
                            'options' => array( 'Google', 'Facebook', 'Instagram', 'TikTok', 'LinkedIn', 'WhatsApp', 'Referrals', 'Physical stores', 'Sales representatives', 'Online marketplaces' ),
                        ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Buying decision maker',
                            'options' => array( 'User', 'Parent/guardian', 'Business owner', 'Manager', 'Procurement department', 'School/institution' ),
                        ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Decision speed',
                            'options' => array( 'Immediate decision', 'Same day', 'A few days', 'A few weeks', 'One month or longer', 'Unsure' ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'Main objections that may prevent purchase' ),
                    ),
                ),

                // ── S11: Competitor Analysis (PDF p.6-7) ──
                array(
                    'title'       => 'Competitor Analysis',
                    'description' => 'Competitive landscape and positioning.',
                    'order'       => 11,
                    'questions'   => array(
                        array( 'type' => 'paragraph', 'label' => 'List main competitors with their location, main offering, and price range.' ),
                        array( 'type' => 'textarea', 'label' => 'Competitor list', 'placeholder' => "Competitor | Location | Main Offering | Price Range\nList each competitor..." ),
                        array( 'type' => 'textarea', 'label' => 'Strongest competitor and why' ),
                        array( 'type' => 'textarea', 'label' => 'What competitors do well' ),
                        array( 'type' => 'textarea', 'label' => 'Gaps or weaknesses' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Competitive strategy',
                            'options' => array( 'Compete on lower price', 'Better value', 'Better quality', 'Faster service', 'Specialist offering', 'Better customer service', 'Convenience', 'Location', 'Branding' ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'Indirect competitors or substitutes' ),
                    ),
                ),

                // ── S12: Pricing and Revenue Model (PDF p.7) ──
                array(
                    'title'       => 'Pricing and Revenue Model',
                    'description' => 'Pricing strategy and revenue sources.',
                    'order'       => 12,
                    'questions'   => array(
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Pricing method',
                            'options' => array( 'Competitor pricing', 'Cost plus margin', 'Customer research', 'Industry standard', 'Supplier recommendation', 'Personal estimate', 'Existing prices', 'Not yet decided' ),
                        ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Pricing position',
                            'options' => array( 'Budget option', 'Affordable value', 'Similar to competitors', 'Premium', 'Specialist high-value' ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'Discounts, payment plans or credit offered' ),
                        array( 'type' => 'text', 'label' => 'Expected average sale per customer' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Revenue streams',
                            'options' => array( 'Product sales', 'Service fees', 'Consultation fees', 'Subscriptions', 'Membership fees', 'Contracts', 'Commission', 'Rental income', 'Delivery fees', 'Installation fees', 'Training fees', 'Maintenance fees', 'Licensing', 'Advertising' ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'Revenue stream details', 'placeholder' => "Revenue Stream | Price/Fee | Monthly Qty | Monthly Income" ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Revenue type',
                            'options' => array( 'Recurring income', 'Partly recurring', 'Once-off income', 'Depends on one major customer/contract' ),
                        ),
                    ),
                ),

                // ── S13: Marketing and Sales Strategy (PDF p.7-8) ──
                array(
                    'title'       => 'Marketing and Sales Strategy',
                    'description' => 'Marketing platforms, content, and sales approach.',
                    'order'       => 13,
                    'questions'   => array(
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Marketing platforms',
                            'options' => array( 'Facebook', 'Instagram', 'TikTok', 'LinkedIn', 'YouTube', 'Google', 'Website', 'Email', 'WhatsApp', 'Flyers', 'Radio', 'Newspapers', 'Events/markets', 'Referrals', 'Partnerships', 'Sales representatives' ),
                        ),
                        array( 'type' => 'text', 'label' => 'Best expected marketing channel' ),
                        array( 'type' => 'text', 'label' => 'Monthly marketing budget' ),
                        array( 'type' => 'radio', 'label' => 'Paid advertising planned?', 'options' => array( 'Paid advertising planned', 'No paid advertising', 'Possibly' ) ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Marketing content types',
                            'options' => array( 'Product ads', 'Educational content', 'Videos', 'Testimonials', 'Customer success stories', 'Promotions', 'Before-and-after results' ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'Launch promotion or introductory offer' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Sales channels',
                            'options' => array( 'Physical premises', 'Website', 'Online store', 'WhatsApp', 'Social media', 'Sales representative', 'Telephone', 'Customer premises', 'Retail partners' ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'Describe the sales process from enquiry to payment' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Who handles sales?',
                            'options' => array( 'Owner handles sales', 'Employee', 'Sales representative', 'External agent', 'Automated system' ),
                        ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Payment methods accepted',
                            'options' => array( 'Cash', 'EFT', 'Card', 'Online payment', 'Debit order', 'Credit' ),
                        ),
                    ),
                ),

                // ── S14: Customer Service and Retention (PDF p.8-9) ──
                array(
                    'title'       => 'Customer Service and Retention',
                    'description' => 'Customer service approach and retention strategies.',
                    'order'       => 14,
                    'questions'   => array(
                        array( 'type' => 'textarea', 'label' => 'Customer service approach' ),
                        array( 'type' => 'textarea', 'label' => 'Guarantees, warranties or refund policies' ),
                        array( 'type' => 'textarea', 'label' => 'Complaint handling process' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Customer retention methods',
                            'options' => array( 'Follow-up messages', 'Email marketing', 'WhatsApp communication', 'Loyalty programme', 'Subscriptions', 'Discounts', 'Referral rewards', 'Excellent service' ),
                        ),
                        array( 'type' => 'radio', 'label' => 'Customer database', 'options' => array( 'Customer database maintained', 'No customer database' ) ),
                    ),
                ),

                // ── S15: Location, Premises and Operations (PDF p.9-10) ──
                array(
                    'title'       => 'Location, Premises and Operations',
                    'description' => 'Business location, premises, and daily operations.',
                    'order'       => 15,
                    'questions'   => array(
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Premises type',
                            'options' => array( 'Home-based', 'Rented shop', 'Office', 'Workshop', 'Factory', 'Warehouse', 'Shared workspace', 'Mobile unit', 'Customer premises', 'Online only' ),
                        ),
                        array( 'type' => 'radio', 'label' => 'Location status', 'options' => array( 'Location secured', 'Still looking', 'Not required' ) ),
                        array( 'type' => 'textarea', 'label' => 'Why is this location suitable?' ),
                        array( 'type' => 'text', 'label' => 'Monthly rent and deposit' ),
                        array( 'type' => 'textarea', 'label' => 'Renovations required and estimated cost' ),
                        array( 'type' => 'textarea', 'label' => 'Describe daily operations' ),
                        array( 'type' => 'text', 'label' => 'Operating hours' ),
                        array( 'type' => 'textarea', 'label' => 'Main steps in delivering the offering' ),
                        array( 'type' => 'text', 'label' => 'Expected capacity per day/week/month' ),
                        array( 'type' => 'textarea', 'label' => 'Activities completed internally' ),
                        array( 'type' => 'textarea', 'label' => 'Activities outsourced' ),
                        array( 'type' => 'radio', 'label' => 'Operating procedures', 'options' => array( 'Operating procedures available', 'Partly available', 'Not available' ) ),
                    ),
                ),

                // ── S16: Equipment, Technology, Suppliers and Stock (PDF p.10-11) ──
                array(
                    'title'       => 'Equipment, Technology, Suppliers and Stock',
                    'description' => 'Assets, technology, and supply chain.',
                    'order'       => 16,
                    'questions'   => array(
                        array( 'type' => 'paragraph', 'label' => 'List equipment and assets needed with quantities and costs.' ),
                        array( 'type' => 'textarea', 'label' => 'Equipment and assets list', 'placeholder' => "Equipment/Asset | Quantity | Cost Each | Total" ),
                        array( 'type' => 'textarea', 'label' => 'Assets already owned' ),
                        array( 'type' => 'textarea', 'label' => 'Equipment to be leased or financed' ),
                        array( 'type' => 'textarea', 'label' => 'Vehicle requirement' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Technology and systems needed',
                            'options' => array( 'Computer/laptop', 'Mobile phone', 'Printer', 'Internet', 'Point-of-sale', 'Card machine', 'Accounting software', 'Booking system', 'Customer database', 'Website', 'Online store', 'Cloud storage' ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'Technology details', 'placeholder' => "Technology/System | Once-off Cost | Monthly Cost" ),
                        array( 'type' => 'textarea', 'label' => 'Suppliers', 'placeholder' => "Supplier | Product/Service | Location | Payment Terms" ),
                        array( 'type' => 'checkbox', 'label' => 'Supply chain resilience', 'options' => array( 'Depends on one supplier', 'Backup suppliers available' ) ),
                        array( 'type' => 'checkbox', 'label' => 'Stock requirements', 'options' => array( 'Stock required', 'Products/materials imported' ) ),
                        array( 'type' => 'textarea', 'label' => 'Opening stock cost and purchase frequency' ),
                    ),
                ),

                // ── S17: Management, Staffing and Organisation (PDF p.11-12) ──
                array(
                    'title'       => 'Management, Staffing and Organisation',
                    'description' => 'Who will manage and operate the business.',
                    'order'       => 17,
                    'questions'   => array(
                        array( 'type' => 'text', 'label' => 'Who will manage the business?' ),
                        array( 'type' => 'textarea', 'label' => 'Owner/management experience and qualifications' ),
                        array( 'type' => 'paragraph', 'label' => 'List staff positions, numbers, salaries, and start dates.' ),
                        array( 'type' => 'textarea', 'label' => 'Staffing plan', 'placeholder' => "Position | Number | Monthly Salary | Start Date" ),
                        array( 'type' => 'textarea', 'label' => 'Staff training required' ),
                        array( 'type' => 'textarea', 'label' => 'Missing skills' ),
                        array( 'type' => 'radio', 'label' => 'Can business operate without owner?', 'options' => array( 'Business can operate without owner', 'Partly', 'No' ) ),
                        array( 'type' => 'paragraph', 'label' => 'List key roles, who is responsible, and their main responsibilities.' ),
                        array( 'type' => 'textarea', 'label' => 'Key roles and responsibilities', 'placeholder' => "Role | Person Responsible | Main Responsibilities" ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Professional advisors',
                            'options' => array( 'Accountant', 'Bookkeeper', 'Tax practitioner', 'Attorney', 'Business consultant', 'Marketing specialist', 'IT support', 'Health and safety consultant' ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'Business functions and responsible persons' ),
                    ),
                ),

                // ── S18: Startup Costs and Monthly Expenses (PDF p.12-13) ──
                array(
                    'title'       => 'Startup Costs and Monthly Expenses',
                    'description' => 'Estimated startup and monthly costs.',
                    'order'       => 18,
                    'questions'   => array(
                        array( 'type' => 'paragraph', 'label' => 'List all startup costs with estimated amounts.' ),
                        array( 'type' => 'textarea', 'label' => 'Startup costs list', 'placeholder' => "Startup Cost | Estimated Amount" ),
                        array( 'type' => 'text', 'label' => 'Estimated total startup cost' ),
                        array( 'type' => 'text', 'label' => 'Startup costs already paid' ),
                        array( 'type' => 'paragraph', 'label' => 'List all monthly expenses with estimated amounts.' ),
                        array( 'type' => 'textarea', 'label' => 'Monthly expenses list', 'placeholder' => "Monthly Expense | Estimated Amount" ),
                        array( 'type' => 'text', 'label' => 'Estimated total monthly expenses' ),
                    ),
                ),

                // ── S19: Sales Forecast and Funding (PDF p.13-14) ──
                array(
                    'title'       => 'Sales Forecast and Funding',
                    'description' => 'Revenue projections and funding requirements.',
                    'order'       => 19,
                    'questions'   => array(
                        array( 'type' => 'text', 'label' => 'Average customers/orders per month' ),
                        array( 'type' => 'text', 'label' => 'Average sale per customer' ),
                        array( 'type' => 'textarea', 'label' => 'Expected monthly and annual revenue' ),
                        array( 'type' => 'textarea', 'label' => 'Monthly sales forecast', 'placeholder' => "Month | Expected Sales" ),
                        array( 'type' => 'textarea', 'label' => 'Assumptions supporting the forecast' ),
                        array( 'type' => 'text', 'label' => 'Owner funds available' ),
                        array( 'type' => 'text', 'label' => 'External funding required' ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Funding sources',
                            'options' => array( 'Bank loan', 'Investor funding', 'Government funding', 'Asset finance', 'Vehicle finance', 'Partner contribution', 'Family/friends', 'Personal loan' ),
                        ),
                        array( 'type' => 'paragraph', 'label' => 'List how funding will be used.' ),
                        array( 'type' => 'textarea', 'label' => 'Use of funding', 'placeholder' => "Use of Funding | Amount" ),
                        array( 'type' => 'radio', 'label' => 'Funding status', 'options' => array( 'Funding approved', 'Application in progress', 'Not yet applied' ) ),
                        array( 'type' => 'textarea', 'label' => 'Owner contribution details' ),
                    ),
                ),

                // ── S20: Loan and Financial Targets (PDF p.15) ──
                array(
                    'title'       => 'Loan and Financial Targets',
                    'description' => 'Loan requirements and financial targets.',
                    'order'       => 20,
                    'questions'   => array(
                        array( 'type' => 'text', 'label' => 'Requested loan amount' ),
                        array( 'type' => 'text', 'label' => 'Preferred repayment period' ),
                        array( 'type' => 'text', 'label' => 'Interest rate if known' ),
                        array( 'type' => 'text', 'label' => 'Maximum affordable monthly repayment' ),
                        array( 'type' => 'textarea', 'label' => 'Collateral/security available' ),
                        array( 'type' => 'paragraph', 'label' => 'List financial targets.' ),
                        array( 'type' => 'textarea', 'label' => 'Financial targets', 'placeholder' => "Financial Target | Target" ),
                        array( 'type' => 'text', 'label' => 'Monthly profit required to make the business worthwhile' ),
                        array(
                            'type'    => 'radio',
                            'label'   => 'Time to profitability',
                            'options' => array( 'Profit needed under 1 month', '1-3 months', '4-6 months', '7-12 months', 'More than 12 months' ),
                        ),
                    ),
                ),

                // ── S21: Risks and SWOT Analysis (PDF p.16) ──
                array(
                    'title'       => 'Risks and SWOT Analysis',
                    'description' => 'Business risks and SWOT analysis.',
                    'order'       => 21,
                    'questions'   => array(
                        array( 'type' => 'paragraph', 'label' => 'Identify risks, their possible impact, and planned response.' ),
                        array( 'type' => 'textarea', 'label' => 'Risk register', 'placeholder' => "Risk | Possible Impact | Planned Response" ),
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Key business risks',
                            'options' => array( 'Low sales', 'Strong competition', 'Cash-flow shortages', 'High operating costs', 'Supplier problems', 'Staff shortages', 'Equipment failure', 'Legal/compliance problems', 'Technology failure', 'Economic conditions' ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'What could cause the business to fail?' ),
                        array( 'type' => 'textarea', 'label' => 'What action will be taken if sales are lower than expected?' ),
                        array( 'type' => 'textarea', 'label' => 'Strengths', 'required' => true ),
                        array( 'type' => 'textarea', 'label' => 'Weaknesses', 'required' => true ),
                        array( 'type' => 'textarea', 'label' => 'Opportunities', 'required' => true ),
                        array( 'type' => 'textarea', 'label' => 'Threats', 'required' => true ),
                    ),
                ),

                // ── S22: Growth Strategy and Implementation (PDF p.16-17) ──
                array(
                    'title'       => 'Growth Strategy and Implementation',
                    'description' => 'Plans for future growth.',
                    'order'       => 22,
                    'questions'   => array(
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Growth strategies',
                            'options' => array( 'More customers', 'Additional offerings', 'New branches', 'New geographic areas', 'Online growth', 'Staff expansion', 'Franchising', 'Licensing', 'Partnerships', 'Exporting' ),
                        ),
                        array( 'type' => 'text', 'label' => 'When is expansion expected to begin?' ),
                        array( 'type' => 'textarea', 'label' => 'Resources required for growth' ),
                        array( 'type' => 'radio', 'label' => 'Franchising/licensing potential', 'options' => array( 'Can be franchised/licensed', 'Possibly', 'No' ) ),
                        array( 'type' => 'radio', 'label' => 'Can operate without owner?', 'options' => array( 'Yes', 'Possibly', 'No' ) ),
                        array( 'type' => 'paragraph', 'label' => 'List implementation actions, responsible persons, target dates, and status.' ),
                        array( 'type' => 'textarea', 'label' => 'Implementation timeline', 'placeholder' => "Action | Person Responsible | Target Date | Status\nList actions..." ),
                    ),
                ),

                // ── S23: Business Plan Requirements (PDF p.17) ──
                array(
                    'title'       => 'Business Plan Requirements',
                    'description' => 'Sections to include in the business plan.',
                    'order'       => 23,
                    'questions'   => array(
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Required sections',
                            'options' => array( 'Executive summary', 'Business description', 'Vision, mission and objectives', 'Ownership and legal structure', 'Products and services', 'Industry analysis', 'Market analysis', 'Target customer profile', 'Competitor analysis', 'SWOT analysis', 'Marketing strategy', 'Sales strategy', 'Operations plan', 'Management and staffing', 'Organisational structure', 'Startup cost estimate', 'Sales forecast', 'Profit-and-loss forecast', 'Cash-flow forecast', 'Break-even analysis', 'Funding requirement', 'Loan repayment information', 'Risk assessment', 'Growth strategy', 'Implementation timeline', 'Supporting appendices' ),
                        ),
                    ),
                ),

                // ── S24: Supporting Documents and Additional Comments (PDF p.17-18) ──
                array(
                    'title'       => 'Supporting Documents and Additional Comments',
                    'description' => 'Documents available and any final comments.',
                    'order'       => 24,
                    'questions'   => array(
                        array(
                            'type'    => 'checkbox',
                            'label'   => 'Available supporting documents',
                            'options' => array( 'Company registration', 'Owner ID documents', 'Tax documents', 'Licences/permits', 'Owner/management CVs', 'Qualifications', 'Product photos', 'Product catalogue', 'Price list', 'Supplier quotations', 'Equipment quotations', 'Rental quotation/lease', 'Vehicle quotations', 'Financial statements', 'Bank statements', 'Sales records', 'Customer contracts', 'Letters of intent', 'Market research', 'Competitor information', 'Logo/branding files', 'Existing business plan', 'Funding documents' ),
                        ),
                        array( 'type' => 'textarea', 'label' => 'Relevant links or additional information', 'placeholder' => 'Paste any relevant URLs...' ),
                        array( 'type' => 'textarea', 'label' => 'Additional comments', 'placeholder' => 'Any other information you would like to share...' ),
                    ),
                ),

            ),
        );
    }
}

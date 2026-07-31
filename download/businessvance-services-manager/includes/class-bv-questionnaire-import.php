<?php
/**
 * BusinessVance Questionnaire Import
 *
 * Imports pre-built questionnaire templates for Market Research and Business Plan services.
 *
 * @package BusinessVance_Services_Manager
 * @since   2.0.3
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
            'version'     => '1.0',
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

    /**
     * Market Research Report Questionnaire definition.
     *
     * @since  2.0.3
     * @return array
     */
    private static function get_market_research_data() {
        return array(
            'name'        => 'Market Research Report Questionnaire',
            'slug'        => 'market-research-report-questionnaire',
            'description' => 'Comprehensive market research questionnaire covering business overview, target customers, competition, pricing, marketing channels and market trends.',
            'sections'    => array(

                // 1 ── Client Information
                array(
                    'title'       => 'Client Information',
                    'description' => '',
                    'order'       => 1,
                    'questions'   => array(
                        array( 'type' => 'text', 'label' => 'Full name', 'required' => true, 'placeholder' => 'e.g. John Doe' ),
                        array( 'type' => 'text', 'label' => 'Business name', 'required' => true, 'placeholder' => 'e.g. BusinessVance (Pty) Ltd' ),
                        array( 'type' => 'email', 'label' => 'Email address', 'required' => true, 'placeholder' => 'e.g. john@company.co.za' ),
                        array( 'type' => 'phone', 'label' => 'Contact number', 'required' => true, 'placeholder' => 'e.g. +27 82 123 4567' ),
                        array( 'type' => 'text', 'label' => 'City / Province / Country', 'required' => true, 'placeholder' => 'e.g. Johannesburg, Gauteng, South Africa' ),
                        array( 'type' => 'select', 'label' => 'Preferred report language', 'required' => true, 'options' => array( 'English', 'Afrikaans', 'Zulu', 'Xhosa', 'Other' ), 'help_text' => 'Select the language in which you would like to receive the final report.' ),
                        array( 'type' => 'date', 'label' => 'Preferred delivery date', 'required' => true, 'help_text' => 'The date by which you need the report completed.' ),
                    ),
                ),

                // 2 ── Business Overview
                array(
                    'title'       => 'Business Overview',
                    'description' => 'Please provide an overview of your business or business idea.',
                    'order'       => 2,
                    'questions'   => array(
                        array( 'type' => 'textarea', 'label' => 'Briefly describe the business or business idea', 'required' => true, 'placeholder' => 'Provide a brief overview of your business concept...' ),
                        array( 'type' => 'textarea', 'label' => 'What products or services do you offer or plan to offer?', 'required' => true, 'placeholder' => 'List all products and services...' ),
                        array( 'type' => 'textarea', 'label' => 'What problem does your business solve?', 'required' => true, 'placeholder' => 'Describe the main problem your business addresses...' ),
                        array( 'type' => 'checkbox', 'label' => 'What stage is the business currently in?', 'required' => true, 'options' => array( 'Idea stage', 'Research stage', 'Planning stage', 'Pre-launch', 'Already operating', 'Expanding', 'Launching a new product or service' ) ),
                        array( 'type' => 'text', 'label' => 'When did the business start, or when do you plan to launch?', 'help_text' => 'Enter the actual or planned start date.' ),
                    ),
                ),

                // 3 ── Purpose of the Market Research
                array(
                    'title'       => 'Purpose of the Market Research',
                    'description' => 'Help us understand what you need from this research report.',
                    'order'       => 3,
                    'questions'   => array(
                        array( 'type' => 'checkbox', 'label' => 'What do you want the market research report to help you decide?', 'required' => true, 'options' => array( 'Whether there is sufficient demand', 'Who the target customer should be', 'What prices customers may accept', 'Which area or location to target', 'How large the market may be', 'What customers want', 'Which marketing channels to use', 'How to position the business', 'Whether to launch a new product or service', 'Whether to expand into a new market', 'Other' ) ),
                        array( 'type' => 'textarea', 'label' => 'What are the main questions you want answered?', 'required' => true, 'placeholder' => 'List the key questions you need the research to answer...' ),
                        array( 'type' => 'radio', 'label' => 'Is there a particular decision deadline?', 'options' => array( 'Yes', 'No' ), 'help_text' => 'If yes, please specify the deadline date below.' ),
                    ),
                ),

                // 4 ── Products and Services
                array(
                    'title'       => 'Products and Services',
                    'description' => 'Details about the products or services to be researched.',
                    'order'       => 4,
                    'questions'   => array(
                        array( 'type' => 'textarea', 'label' => 'List the products or services to be researched', 'required' => true, 'placeholder' => "Product/Service | Current/Planned | Priority | Price\nList each item on a new line..." ),
                        array( 'type' => 'text', 'label' => 'Which product or service is the main focus of the research?', 'required' => true, 'placeholder' => 'e.g. Accounting services' ),
                        array( 'type' => 'checkbox', 'label' => 'How would you describe the offering?', 'options' => array( 'New to the market', 'Already available from competitors', 'Improved version of an existing product', 'Specialist service', 'Luxury product or service', 'Low-cost option', 'Other' ) ),
                        array( 'type' => 'textarea', 'label' => 'What makes the product or service different?', 'placeholder' => 'Describe your unique selling points...' ),
                        array( 'type' => 'checkbox', 'label' => 'How often will customers purchase?', 'options' => array( 'Once-off purchase', 'Frequent repeat purchase', 'Occasional repeat purchase', 'Monthly subscription', 'Annual subscription', 'Contract-based', 'Unsure' ) ),
                    ),
                ),

                // 5 ── Geographic Market
                array(
                    'title'       => 'Geographic Market',
                    'description' => 'Define the geographic area for the research.',
                    'order'       => 5,
                    'questions'   => array(
                        array( 'type' => 'checkbox', 'label' => 'Which market should the report focus on?', 'required' => true, 'options' => array( 'Specific suburb', 'Specific town or city', 'District or municipality', 'Province', 'South Africa', 'Southern Africa', 'International market', 'Online market' ) ),
                        array( 'type' => 'textarea', 'label' => 'List the main locations to be researched', 'placeholder' => 'List specific areas, towns or suburbs...' ),
                        array( 'type' => 'radio', 'label' => 'Will customers visit a physical location?', 'required' => true, 'options' => array( 'Yes', 'No', 'Both physical and online' ) ),
                        array( 'type' => 'checkbox', 'label' => 'How far are customers likely to travel?', 'options' => array( 'Less than 5 km', '5-10 km', '10-25 km', 'More than 25 km', 'Not applicable', 'Unsure' ) ),
                        array( 'type' => 'textarea', 'label' => 'Are there specific areas you want excluded from the research?', 'placeholder' => 'List any areas to exclude...' ),
                    ),
                ),

                // 6 ── Target Customer
                array(
                    'title'       => 'Target Customer',
                    'description' => 'Help us understand your ideal customer profile.',
                    'order'       => 6,
                    'questions'   => array(
                        array( 'type' => 'textarea', 'label' => 'Who do you believe your ideal customer is?', 'required' => true, 'placeholder' => 'Describe your ideal customer in detail...' ),
                        array( 'type' => 'checkbox', 'label' => 'Is the business aimed mainly at', 'required' => true, 'options' => array( 'Individual consumers', 'Families', 'Parents', 'Children or teenagers', 'Students', 'Professionals', 'Small businesses', 'Large businesses', 'Government', 'Schools or educational institutions', 'Non-profit organisations', 'Other' ) ),
                        array( 'type' => 'checkbox', 'label' => 'What is the typical customer age range?', 'options' => array( 'Under 18', '18-24', '25-34', '35-44', '45-54', '55-64', '65+', 'Not age-specific' ) ),
                        array( 'type' => 'checkbox', 'label' => 'Which customer characteristics are most important?', 'options' => array( 'Income', 'Occupation', 'Location', 'Education level', 'Family status', 'Lifestyle', 'Business size', 'Industry', 'Buying behaviour', 'Other' ) ),
                        array( 'type' => 'text', 'label' => 'What income level or budget is typical for your ideal customer?', 'placeholder' => 'e.g. R15,000 - R30,000 per month' ),
                        array( 'type' => 'checkbox', 'label' => 'Who normally makes the buying decision?', 'options' => array( 'User of the product', 'Parent or guardian', 'Business owner', 'Manager', 'Procurement department', 'School or institution', 'Other' ) ),
                    ),
                ),

                // 7 ── Customer Needs and Problems
                array(
                    'title'       => 'Customer Needs and Problems',
                    'description' => 'Understanding customer pain points and motivations.',
                    'order'       => 7,
                    'questions'   => array(
                        array( 'type' => 'textarea', 'label' => 'What main problem does the customer experience?', 'required' => true, 'placeholder' => 'Describe the primary problem or need...' ),
                        array( 'type' => 'radio', 'label' => 'How serious or urgent is this problem?', 'required' => true, 'options' => array( 'Low importance', 'Moderate importance', 'High importance', 'Urgent' ) ),
                        array( 'type' => 'textarea', 'label' => 'How are customers currently solving the problem?', 'placeholder' => 'Describe current solutions customers use...' ),
                        array( 'type' => 'textarea', 'label' => 'What do customers dislike about current solutions?', 'placeholder' => 'List complaints or frustrations...' ),
                        array( 'type' => 'checkbox', 'label' => 'What benefits are customers likely to value most?', 'options' => array( 'Lower price', 'Better quality', 'Faster service', 'Convenience', 'Reliability', 'Personal service', 'Specialist knowledge', 'Location', 'Flexibility', 'Better results', 'Other' ) ),
                        array( 'type' => 'checkbox', 'label' => 'What might prevent customers from buying?', 'options' => array( 'Price', 'Lack of trust', 'Low awareness', 'Competition', 'Distance', 'Lack of urgency', 'Existing contracts', 'Preference for familiar brands', 'Other' ) ),
                    ),
                ),

                // 8 ── Customer Buying Behaviour
                array(
                    'title'       => 'Customer Buying Behaviour',
                    'description' => 'How customers find and purchase products or services.',
                    'order'       => 8,
                    'questions'   => array(
                        array( 'type' => 'checkbox', 'label' => 'How often are customers likely to buy?', 'options' => array( 'Daily', 'Weekly', 'Monthly', 'A few times per year', 'Once-off', 'Contract basis', 'Unsure' ) ),
                        array( 'type' => 'checkbox', 'label' => 'Where do customers currently buy similar products or services?', 'options' => array( 'Physical stores', 'Online shops', 'Social media', 'Directly from suppliers', 'Through referrals', 'At markets or events', 'Through agents', 'Other' ) ),
                        array( 'type' => 'checkbox', 'label' => 'How do customers normally search for this product or service?', 'options' => array( 'Google', 'Facebook', 'Instagram', 'TikTok', 'LinkedIn', 'WhatsApp', 'Word of mouth', 'Business directories', 'In-store visits', 'Other' ) ),
                        array( 'type' => 'checkbox', 'label' => 'What factors are most likely to influence the purchase?', 'options' => array( 'Price', 'Quality', 'Reviews', 'Recommendations', 'Brand reputation', 'Convenience', 'Speed', 'Customer service', 'Guarantees', 'Qualifications or experience', 'Other' ) ),
                        array( 'type' => 'radio', 'label' => 'Is the purchase usually planned or urgent?', 'options' => array( 'Planned well in advance', 'Short-notice purchase', 'Emergency purchase', 'Impulse purchase', 'Depends on the customer' ) ),
                    ),
                ),

                // 9 ── Demand Validation
                array(
                    'title'       => 'Demand Validation',
                    'description' => 'Evidence that there is demand for your product or service.',
                    'order'       => 9,
                    'questions'   => array(
                        array( 'type' => 'checkbox', 'label' => 'What evidence do you currently have that there is demand?', 'options' => array( 'Customer enquiries', 'Existing sales', 'Pre-orders', 'Survey results', 'Social media engagement', 'Website traffic', 'Waiting list', 'Competitor activity', 'Industry experience', 'No evidence yet', 'Other' ) ),
                        array( 'type' => 'text', 'label' => 'Approximately how many potential customers have shown interest?', 'placeholder' => 'e.g. 50' ),
                        array( 'type' => 'radio', 'label' => 'Have any customers already paid or committed to buy?', 'options' => array( 'Yes', 'No' ) ),
                        array( 'type' => 'radio', 'label' => 'Have you tested the idea with a trial, sample, pilot or minimum product?', 'options' => array( 'Yes', 'No' ) ),
                        array( 'type' => 'radio', 'label' => 'Have you conducted customer surveys or interviews?', 'options' => array( 'Yes', 'No' ) ),
                    ),
                ),

                // 10 ── Market Size
                array(
                    'title'       => 'Market Size',
                    'description' => 'Understanding the size and growth of your target market.',
                    'order'       => 10,
                    'questions'   => array(
                        array( 'type' => 'text', 'label' => 'Approximately how many potential customers do you believe are in the target market?', 'placeholder' => 'e.g. 10,000' ),
                        array( 'type' => 'textarea', 'label' => 'How did you estimate this number?', 'placeholder' => 'Describe how you arrived at this estimate...' ),
                        array( 'type' => 'radio', 'label' => 'Is the target market', 'options' => array( 'Growing', 'Stable', 'Declining', 'Unknown' ) ),
                        array( 'type' => 'checkbox', 'label' => 'Are you targeting', 'options' => array( 'Broad market', 'Specialised niche', 'Premium market', 'Budget market', 'Mass market', 'Unsure' ) ),
                        array( 'type' => 'text', 'label' => 'What percentage of the market would you realistically like to reach in the first year?', 'placeholder' => 'e.g. 5%' ),
                    ),
                ),

                // 11 ── Industry Information
                array(
                    'title'       => 'Industry Information',
                    'description' => 'Industry context and regulatory environment.',
                    'order'       => 11,
                    'questions'   => array(
                        array( 'type' => 'text', 'label' => 'What industry does the business operate in?', 'required' => true, 'placeholder' => 'e.g. Professional consulting' ),
                        array( 'type' => 'radio', 'label' => 'Do you have experience in this industry?', 'options' => array( 'Yes', 'No' ) ),
                        array( 'type' => 'textarea', 'label' => 'What important changes are taking place in the industry?', 'placeholder' => 'Describe key industry changes...' ),
                        array( 'type' => 'radio', 'label' => 'Are new technologies affecting the industry?', 'options' => array( 'Yes', 'No', 'Unsure' ) ),
                        array( 'type' => 'textarea', 'label' => 'List any industry associations, professional bodies or important organisations', 'placeholder' => 'List organisations...' ),
                        array( 'type' => 'textarea', 'label' => 'List any licences, regulations or professional requirements', 'placeholder' => 'List regulatory requirements...' ),
                    ),
                ),

                // 12 ── Market Trends
                array(
                    'title'       => 'Market Trends',
                    'description' => 'Trends affecting market demand.',
                    'order'       => 12,
                    'questions'   => array(
                        array( 'type' => 'textarea', 'label' => 'Which trends could increase demand?', 'placeholder' => 'List trends that may increase demand...' ),
                        array( 'type' => 'textarea', 'label' => 'Which trends could reduce demand?', 'placeholder' => 'List trends that may reduce demand...' ),
                        array( 'type' => 'checkbox', 'label' => 'Is demand influenced by', 'options' => array( 'The economy', 'Interest rates', 'Technology', 'Population growth', 'Lifestyle changes', 'Education trends', 'Government policy', 'Environmental concerns', 'Social media trends', 'Other' ) ),
                        array( 'type' => 'radio', 'label' => 'Is the market affected by seasonal demand?', 'options' => array( 'Yes', 'No', 'Unsure' ) ),
                        array( 'type' => 'textarea', 'label' => 'Are there future changes that could affect the market?', 'placeholder' => 'Describe upcoming changes...' ),
                    ),
                ),

                // 13 ── Competitors and Alternatives
                array(
                    'title'       => 'Competitors and Alternatives',
                    'description' => 'Analysis of the competitive landscape.',
                    'order'       => 13,
                    'questions'   => array(
                        array( 'type' => 'textarea', 'label' => 'List the main competitors you already know about', 'required' => true, 'placeholder' => "Competitor | Location/Website | Main Offering | Price Range\nList each competitor..." ),
                        array( 'type' => 'checkbox', 'label' => 'Competitors are mainly', 'options' => array( 'Local businesses', 'National businesses', 'International businesses', 'Online businesses', 'Informal businesses', 'A combination' ) ),
                        array( 'type' => 'textarea', 'label' => 'What do competitors do well?', 'placeholder' => 'List competitor strengths...' ),
                        array( 'type' => 'textarea', 'label' => 'What weaknesses or gaps have you noticed?', 'placeholder' => 'List competitor weaknesses or market gaps...' ),
                        array( 'type' => 'textarea', 'label' => 'What alternatives could customers use instead?', 'placeholder' => 'List alternative products or solutions...' ),
                        array( 'type' => 'radio', 'label' => 'How competitive is the market?', 'options' => array( 'Low competition', 'Moderate competition', 'High competition', 'Unsure' ) ),
                    ),
                ),

                // 14 ── Pricing Research
                array(
                    'title'       => 'Pricing Research',
                    'description' => 'Pricing analysis and strategy.',
                    'order'       => 14,
                    'questions'   => array(
                        array( 'type' => 'text', 'label' => 'Current or planned price', 'placeholder' => 'e.g. R500' ),
                        array( 'type' => 'text', 'label' => 'Lowest profitable price', 'placeholder' => 'e.g. R350' ),
                        array( 'type' => 'text', 'label' => 'Highest price customers may accept', 'placeholder' => 'e.g. R800' ),
                        array( 'type' => 'textarea', 'label' => 'What prices do competitors charge?', 'placeholder' => 'List competitor prices...' ),
                        array( 'type' => 'checkbox', 'label' => 'Which pricing position do you want?', 'options' => array( 'Lowest-cost option', 'Affordable value option', 'Similar to competitors', 'Premium option', 'Specialist high-value option', 'Unsure' ) ),
                        array( 'type' => 'radio', 'label' => 'Include pricing recommendations?', 'options' => array( 'Yes', 'No' ) ),
                        array( 'type' => 'radio', 'label' => 'Are discounts, bundles or payment plans being considered?', 'options' => array( 'Yes', 'No' ) ),
                    ),
                ),

                // 15 ── Sales Channels
                array(
                    'title'       => 'Sales Channels',
                    'description' => 'How and where customers will purchase.',
                    'order'       => 15,
                    'questions'   => array(
                        array( 'type' => 'checkbox', 'label' => 'Where will customers purchase?', 'options' => array( 'Physical location', 'Website', 'Online store', 'WhatsApp', 'Social media', 'Sales representative', 'Retail partners', 'Markets or events', 'Customer premises', 'Other' ) ),
                        array( 'type' => 'text', 'label' => 'Which sales channel do you expect to generate the most business?', 'placeholder' => 'e.g. Website' ),
                        array( 'type' => 'radio', 'label' => 'Do you already have access to distribution or sales channels?', 'options' => array( 'Yes', 'No' ) ),
                        array( 'type' => 'radio', 'label' => 'Will delivery or transport be required?', 'options' => array( 'Yes', 'No' ) ),
                    ),
                ),

                // 16 ── Marketing Channels
                array(
                    'title'       => 'Marketing Channels',
                    'description' => 'Marketing and advertising platforms.',
                    'order'       => 16,
                    'questions'   => array(
                        array( 'type' => 'checkbox', 'label' => 'Which marketing platforms do you use or plan to use?', 'options' => array( 'Facebook', 'Instagram', 'TikTok', 'LinkedIn', 'YouTube', 'Google Ads', 'Website', 'Email', 'WhatsApp', 'Flyers', 'Local newspapers', 'Radio', 'Events', 'Referrals', 'Partnerships', 'Other' ) ),
                        array( 'type' => 'textarea', 'label' => 'Which platforms does your target customer use most?', 'placeholder' => "Platform | Followers/Contacts | Engagement/Notes\nList platforms..." ),
                        array( 'type' => 'text', 'label' => 'Planned monthly marketing budget', 'placeholder' => 'e.g. R5,000' ),
                        array( 'type' => 'textarea', 'label' => 'Current audience or customer database', 'placeholder' => 'Describe your current audience size and engagement...' ),
                        array( 'type' => 'textarea', 'label' => 'Which marketing activities have worked well in the past?', 'placeholder' => 'List successful marketing activities...' ),
                        array( 'type' => 'textarea', 'label' => 'Which marketing activities have not worked?', 'placeholder' => 'List unsuccessful marketing activities...' ),
                    ),
                ),

                // 17 ── Current Business Performance
                array(
                    'title'       => 'Current Business Performance',
                    'description' => 'Complete this section only if the business is already operating.',
                    'order'       => 17,
                    'questions'   => array(
                        array( 'type' => 'number', 'label' => 'Average customers per month', 'placeholder' => 'e.g. 30' ),
                        array( 'type' => 'text', 'label' => 'Average monthly sales revenue', 'placeholder' => 'e.g. R50,000' ),
                        array( 'type' => 'text', 'label' => 'Average sale per customer', 'placeholder' => 'e.g. R1,500' ),
                        array( 'type' => 'text', 'label' => 'Best-selling product or service', 'placeholder' => 'e.g. Premium consulting package' ),
                        array( 'type' => 'text', 'label' => 'Slowest-selling product or service', 'placeholder' => 'e.g. Basic advisory session' ),
                        array( 'type' => 'text', 'label' => 'Strongest months', 'placeholder' => 'e.g. November, December' ),
                        array( 'type' => 'text', 'label' => 'Weakest months', 'placeholder' => 'e.g. January, February' ),
                        array( 'type' => 'text', 'label' => 'Estimated repeat-customer percentage', 'placeholder' => 'e.g. 40%' ),
                        array( 'type' => 'textarea', 'label' => 'Where do most current customers come from?', 'placeholder' => 'Describe your main customer acquisition channels...' ),
                    ),
                ),

                // 18 ── Research Sources
                array(
                    'title'       => 'Research Sources',
                    'description' => 'Information and documents available for the research.',
                    'order'       => 18,
                    'questions'   => array(
                        array( 'type' => 'checkbox', 'label' => 'Please attach any information or documents already available', 'options' => array( 'Customer survey results', 'Sales records', 'Customer database', 'Social media statistics', 'Website statistics', 'Industry reports', 'Supplier information', 'Competitor price lists', 'Product photographs', 'Customer reviews', 'Previous research', 'Other' ) ),
                        array( 'type' => 'textarea', 'label' => 'Relevant website or social media links', 'placeholder' => 'Paste any relevant URLs...' ),
                    ),
                ),

                // 19 ── Research Focus
                array(
                    'title'       => 'Research Focus',
                    'description' => 'Rate the importance of each research area from 1 to 5, where 5 is most important.',
                    'order'       => 19,
                    'questions'   => array(
                        array( 'type' => 'select', 'label' => 'Market demand importance', 'options' => array( '1 - Low', '2', '3', '4', '5 - High' ) ),
                        array( 'type' => 'select', 'label' => 'Market size importance', 'options' => array( '1 - Low', '2', '3', '4', '5 - High' ) ),
                        array( 'type' => 'select', 'label' => 'Customer profile importance', 'options' => array( '1 - Low', '2', '3', '4', '5 - High' ) ),
                        array( 'type' => 'select', 'label' => 'Customer needs importance', 'options' => array( '1 - Low', '2', '3', '4', '5 - High' ) ),
                        array( 'type' => 'select', 'label' => 'Buying behaviour importance', 'options' => array( '1 - Low', '2', '3', '4', '5 - High' ) ),
                        array( 'type' => 'select', 'label' => 'Pricing importance', 'options' => array( '1 - Low', '2', '3', '4', '5 - High' ) ),
                        array( 'type' => 'select', 'label' => 'Competitors importance', 'options' => array( '1 - Low', '2', '3', '4', '5 - High' ) ),
                        array( 'type' => 'select', 'label' => 'Industry trends importance', 'options' => array( '1 - Low', '2', '3', '4', '5 - High' ) ),
                        array( 'type' => 'select', 'label' => 'Best location importance', 'options' => array( '1 - Low', '2', '3', '4', '5 - High' ) ),
                        array( 'type' => 'select', 'label' => 'Marketing channels importance', 'options' => array( '1 - Low', '2', '3', '4', '5 - High' ) ),
                        array( 'type' => 'select', 'label' => 'Growth opportunities importance', 'options' => array( '1 - Low', '2', '3', '4', '5 - High' ) ),
                    ),
                ),

                // 20 ── Expected Outcome
                array(
                    'title'       => 'Expected Outcome',
                    'description' => 'What you expect from the research report.',
                    'order'       => 20,
                    'questions'   => array(
                        array( 'type' => 'textarea', 'label' => 'What would make the market attractive enough for you to proceed?', 'required' => true, 'placeholder' => 'Describe the conditions that would make you proceed...' ),
                        array( 'type' => 'textarea', 'label' => 'What findings would make you reconsider the idea?', 'placeholder' => 'What results would discourage you from proceeding?' ),
                        array( 'type' => 'checkbox', 'label' => 'What would you like the report to recommend?', 'options' => array( 'Proceed as planned', 'Proceed with changes', 'Test the market first', 'Change the target customer', 'Adjust pricing', 'Change the location', 'Improve the offering', 'Do not proceed', 'Other' ) ),
                        array( 'type' => 'textarea', 'label' => 'Is there any other information we should consider?', 'placeholder' => 'Any additional information...' ),
                    ),
                ),
            ),
        );
    }

    /**
     * Business Plan Questionnaire definition.
     *
     * @since  2.0.3
     * @return array
     */
    private static function get_business_plan_data() {
        return array(
            'name'        => 'Business Plan Questionnaire',
            'slug'        => 'business-plan-questionnaire',
            'description' => 'Comprehensive business plan questionnaire covering business concept, market analysis, operations, marketing, management, finances, risks and growth strategy.',
            'sections'    => array(

                // 1 ── Client Information
                array(
                    'title'       => 'Client Information',
                    'description' => '',
                    'order'       => 1,
                    'questions'   => array(
                        array( 'type' => 'text', 'label' => 'Full name', 'required' => true, 'placeholder' => 'e.g. John Doe' ),
                        array( 'type' => 'text', 'label' => 'Business name', 'required' => true, 'placeholder' => 'e.g. BusinessVance (Pty) Ltd' ),
                        array( 'type' => 'email', 'label' => 'Email address', 'required' => true, 'placeholder' => 'e.g. john@company.co.za' ),
                        array( 'type' => 'phone', 'label' => 'Contact number', 'required' => true, 'placeholder' => 'e.g. +27 82 123 4567' ),
                        array( 'type' => 'text', 'label' => 'City / Province / Country', 'required' => true, 'placeholder' => 'e.g. Johannesburg, Gauteng, South Africa' ),
                        array( 'type' => 'radio', 'label' => 'Preferred report language', 'required' => true, 'options' => array( 'English', 'Afrikaans' ) ),
                    ),
                ),

                // 2 ── Purpose of the Business Plan
                array(
                    'title'       => 'Purpose of the Business Plan',
                    'description' => 'Define why you need a business plan and who will read it.',
                    'order'       => 2,
                    'questions'   => array(
                        array( 'type' => 'checkbox', 'label' => 'Purpose', 'required' => true, 'options' => array( 'Start a new business', 'Apply for bank funding', 'Apply for government funding', 'Attract an investor', 'Approach a business partner', 'Expand an existing business', 'Purchase equipment or assets', 'Apply for a lease', 'Guide operations', 'Introduce a new product or service', 'Purchase an existing business', 'Other' ) ),
                        array( 'type' => 'checkbox', 'label' => 'Primary audience', 'options' => array( 'Business owner', 'Bank or lender', 'Investor', 'Funding organisation', 'Government department', 'Business partner', 'Landlord', 'Management team', 'Other' ) ),
                        array( 'type' => 'textarea', 'label' => 'What are the main questions the business plan should answer?', 'required' => true, 'placeholder' => 'List the key questions...' ),
                        array( 'type' => 'date', 'label' => 'Is there a funding, application or presentation deadline?', 'help_text' => 'Enter the deadline if applicable.' ),
                        array( 'type' => 'radio', 'label' => 'Forecast period', 'options' => array( 'One-year plan', 'Three-year plan', 'Five-year plan', 'Other' ) ),
                    ),
                ),

                // 3 ── Business Overview
                array(
                    'title'       => 'Business Overview',
                    'description' => 'General description of the business.',
                    'order'       => 3,
                    'questions'   => array(
                        array( 'type' => 'textarea', 'label' => 'Briefly describe the business or business idea', 'required' => true, 'placeholder' => 'Provide a comprehensive overview...' ),
                        array( 'type' => 'textarea', 'label' => 'What products or services will the business offer?', 'required' => true, 'placeholder' => 'List all products and services...' ),
                        array( 'type' => 'textarea', 'label' => 'What problem does the business solve?', 'required' => true, 'placeholder' => 'Describe the problem...' ),
                        array( 'type' => 'checkbox', 'label' => 'What stage is the business currently in?', 'required' => true, 'options' => array( 'Idea stage', 'Research stage', 'Planning stage', 'Pre-launch', 'Recently launched', 'Operating under 1 year', 'Operating 1-3 years', 'Operating over 3 years', 'Expanding', 'Purchasing an existing business' ) ),
                        array( 'type' => 'date', 'label' => 'Start date or planned launch date' ),
                        array( 'type' => 'checkbox', 'label' => 'Business model', 'options' => array( 'Product-based', 'Service-based', 'Subscription-based', 'Contract-based', 'Commission-based', 'Rental-based' ) ),
                        array( 'type' => 'checkbox', 'label' => 'Business type', 'options' => array( 'Manufacturing', 'Retail', 'Wholesale', 'Online', 'Physical', 'Mobile', 'Combination' ) ),
                    ),
                ),

                // 4 ── Business Name and Legal Structure
                array(
                    'title'       => 'Business Name and Legal Structure',
                    'description' => 'Legal registration and business entity details.',
                    'order'       => 4,
                    'questions'   => array(
                        array( 'type' => 'text', 'label' => 'Proposed or registered business name', 'required' => true, 'placeholder' => 'e.g. BusinessVance (Pty) Ltd' ),
                        array( 'type' => 'text', 'label' => 'Registration number, if applicable', 'placeholder' => 'e.g. 2024/123456/07' ),
                        array( 'type' => 'radio', 'label' => 'Name registration status', 'options' => array( 'Name approved/registered', 'Application in progress', 'Not yet registered' ) ),
                        array( 'type' => 'radio', 'label' => 'Legal structure', 'required' => true, 'options' => array( 'Sole proprietor', 'Private company', 'Partnership', 'Cooperative', 'Non-profit organisation', 'Franchise', 'Other', 'Not yet decided' ) ),
                        array( 'type' => 'textarea', 'label' => 'Why was this structure selected?', 'placeholder' => 'Explain your choice...' ),
                        array( 'type' => 'checkbox', 'label' => 'Tax registration status', 'options' => array( 'Tax registered', 'Tax registration planned', 'VAT registered', 'VAT registration planned', 'Not registered', 'Unsure' ) ),
                        array( 'type' => 'textarea', 'label' => 'Licences, permits or professional registrations required', 'placeholder' => 'List required licences and permits...' ),
                    ),
                ),

                // 5 ── Ownership and Shareholding
                array(
                    'title'       => 'Ownership and Shareholding',
                    'description' => 'Details of all business owners and shareholders.',
                    'order'       => 5,
                    'questions'   => array(
                        array( 'type' => 'heading', 'label' => 'Owners and Shareholders', 'help_text' => 'List all owners/shareholders below.' ),
                        array( 'type' => 'textarea', 'label' => 'Owners/shareholders details', 'placeholder' => "Name | Position | Ownership % | Contribution\nList each owner on a new line..." ),
                        array( 'type' => 'radio', 'label' => 'Shareholders/partnership agreement status', 'options' => array( 'Shareholders/partnership agreement in place', 'No agreement', 'Not applicable', 'Investors may receive ownership' ) ),
                        array( 'type' => 'text', 'label' => 'Who will make final business decisions?', 'required' => true, 'placeholder' => 'e.g. John Doe - Managing Director' ),
                    ),
                ),

                // 6 ── Vision, Mission and Objectives
                array(
                    'title'       => 'Vision, Mission and Objectives',
                    'description' => 'The strategic direction and goals of the business.',
                    'order'       => 6,
                    'questions'   => array(
                        array( 'type' => 'textarea', 'label' => 'Long-term vision', 'required' => true, 'placeholder' => 'Where do you see the business in the long term?' ),
                        array( 'type' => 'textarea', 'label' => 'Mission or main purpose', 'required' => true, 'placeholder' => 'What is the core purpose of the business?' ),
                        array( 'type' => 'checkbox', 'label' => 'Short-term objectives', 'options' => array( 'Launch business', 'Reach break-even', 'Gain target number of customers', 'Achieve sales target', 'Employ staff', 'Secure contracts', 'Build brand awareness', 'Expand product range', 'Open premises', 'Other' ) ),
                        array( 'type' => 'textarea', 'label' => 'Main 12-month objectives', 'placeholder' => 'List key objectives for the first year...' ),
                        array( 'type' => 'textarea', 'label' => 'Three-year goals', 'placeholder' => 'Describe your goals for year three...' ),
                        array( 'type' => 'textarea', 'label' => 'What would success look like after five years?', 'placeholder' => 'Describe your five-year vision...' ),
                    ),
                ),

                // 7 ── Products and Services
                array(
                    'title'       => 'Products and Services',
                    'description' => 'Detailed information about your offerings.',
                    'order'       => 7,
                    'questions'   => array(
                        array( 'type' => 'heading', 'label' => 'Products and Services', 'help_text' => 'Provide details of each product or service.' ),
                        array( 'type' => 'textarea', 'label' => 'Products/services details', 'required' => true, 'placeholder' => "Product/Service | Selling Price | Est. Cost\nList each item..." ),
                        array( 'type' => 'text', 'label' => 'Main product or service focus', 'required' => true, 'placeholder' => 'e.g. Consulting services' ),
                        array( 'type' => 'text', 'label' => 'Most profitable product or service', 'placeholder' => 'e.g. Premium package' ),
                        array( 'type' => 'textarea', 'label' => 'New products or services planned', 'placeholder' => 'Describe planned additions...' ),
                        array( 'type' => 'checkbox', 'label' => 'Supply chain approach', 'options' => array( 'Manufacture internally', 'Purchase from suppliers', 'Import', 'Third-party manufacture', 'Resell finished products', 'Not applicable' ) ),
                        array( 'type' => 'checkbox', 'label' => 'Expected purchase frequency', 'options' => array( 'Once-off purchase', 'Weekly repeat', 'Monthly repeat', 'Occasional repeat', 'Subscription', 'Contract basis', 'Seasonal' ) ),
                    ),
                ),

                // 8 ── Unique Value Proposition
                array(
                    'title'       => 'Unique Value Proposition',
                    'description' => 'What makes your business different from competitors.',
                    'order'       => 8,
                    'questions'   => array(
                        array( 'type' => 'checkbox', 'label' => 'Why should customers choose your business?', 'required' => true, 'options' => array( 'Lower prices', 'Better quality', 'Faster service', 'Better customer service', 'Specialist expertise', 'Unique offering', 'Greater convenience', 'Better location', 'Customisation', 'More experience', 'Better guarantees', 'Other' ) ),
                        array( 'type' => 'textarea', 'label' => 'Strongest customer benefit', 'placeholder' => 'What is the single biggest benefit for customers?' ),
                        array( 'type' => 'checkbox', 'label' => 'Competitive advantages', 'options' => array( 'Qualifications', 'Industry experience', 'Testimonials', 'Reviews', 'Case studies', 'Product testing', 'Guarantees', 'Awards', 'Existing sales', 'Other' ) ),
                    ),
                ),

                // 9 ── Industry Overview and Market Opportunity
                array(
                    'title'       => 'Industry Overview and Market Opportunity',
                    'description' => 'Industry context and evidence of market opportunity.',
                    'order'       => 9,
                    'questions'   => array(
                        array( 'type' => 'text', 'label' => 'Industry', 'required' => true, 'placeholder' => 'e.g. Professional consulting' ),
                        array( 'type' => 'checkbox', 'label' => 'Industry status', 'options' => array( 'Growing', 'Stable', 'Declining', 'Highly competitive', 'New/emerging', 'Seasonal', 'Unsure' ) ),
                        array( 'type' => 'textarea', 'label' => 'Industry trends, technology changes and regulations', 'placeholder' => 'Describe key industry trends...' ),
                        array( 'type' => 'textarea', 'label' => 'Why is there an opportunity for this business?', 'required' => true, 'placeholder' => 'Explain the market opportunity...' ),
                        array( 'type' => 'checkbox', 'label' => 'Evidence of demand', 'options' => array( 'Existing sales', 'Customer enquiries', 'Pre-orders', 'Waiting list', 'Customer surveys', 'Social-media interest', 'Competitor activity', 'Industry research', 'Previous experience', 'Signed contracts', 'No evidence yet', 'Other' ) ),
                        array( 'type' => 'text', 'label' => 'Number of interested or committed customers', 'placeholder' => 'e.g. 25' ),
                        array( 'type' => 'radio', 'label' => 'Demand pattern', 'options' => array( 'Consistent demand', 'Seasonal demand', 'Event-based', 'Contract-based', 'Uncertain' ) ),
                    ),
                ),

                // 10 ── Target Market and Buying Behaviour
                array(
                    'title'       => 'Target Market and Buying Behaviour',
                    'description' => 'Who your customers are and how they buy.',
                    'order'       => 10,
                    'questions'   => array(
                        array( 'type' => 'textarea', 'label' => 'Describe the ideal customer', 'required' => true, 'placeholder' => 'Provide a detailed customer profile...' ),
                        array( 'type' => 'checkbox', 'label' => 'Target customer type', 'options' => array( 'Individuals', 'Families', 'Parents', 'Children/teenagers', 'Students', 'Professionals', 'Small businesses', 'Large businesses', 'Government', 'Schools/institutions', 'Non-profit organisations', 'Other' ) ),
                        array( 'type' => 'checkbox', 'label' => 'Age range', 'options' => array( 'Under 18', '18-24', '25-34', '35-44', '45-54', '55-64', '65+', 'Not age-specific' ) ),
                        array( 'type' => 'text', 'label' => 'Typical income, budget or business size', 'placeholder' => 'e.g. R20,000/month income' ),
                        array( 'type' => 'textarea', 'label' => 'Geographic areas to target', 'placeholder' => 'List areas, cities or regions...' ),
                        array( 'type' => 'textarea', 'label' => 'Customer problem or need', 'required' => true, 'placeholder' => 'What problem does the customer need solved?' ),
                        array( 'type' => 'checkbox', 'label' => 'What customers value', 'options' => array( 'Price', 'Quality', 'Convenience', 'Speed', 'Reliability', 'Customer service', 'Expertise', 'Reputation', 'Flexibility', 'Results' ) ),
                        array( 'type' => 'checkbox', 'label' => 'Where customers search', 'options' => array( 'Google', 'Facebook', 'Instagram', 'TikTok', 'LinkedIn', 'WhatsApp', 'Referrals', 'Physical stores', 'Sales representatives', 'Online marketplaces', 'Other' ) ),
                        array( 'type' => 'checkbox', 'label' => 'Buying decision maker', 'options' => array( 'User', 'Parent/guardian', 'Business owner', 'Manager', 'Procurement department', 'School/institution', 'Other' ) ),
                        array( 'type' => 'radio', 'label' => 'Decision speed', 'options' => array( 'Immediate decision', 'Same day', 'A few days', 'A few weeks', 'One month or longer', 'Unsure' ) ),
                        array( 'type' => 'textarea', 'label' => 'Main objections that may prevent purchase', 'placeholder' => 'List likely objections...' ),
                    ),
                ),

                // 11 ── Competitor Analysis
                array(
                    'title'       => 'Competitor Analysis',
                    'description' => 'Analysis of direct and indirect competition.',
                    'order'       => 11,
                    'questions'   => array(
                        array( 'type' => 'heading', 'label' => 'Competitors', 'help_text' => 'List all known competitors.' ),
                        array( 'type' => 'textarea', 'label' => 'Competitor details', 'required' => true, 'placeholder' => "Competitor | Location | Main Offering | Price Range\nList each competitor..." ),
                        array( 'type' => 'textarea', 'label' => 'Strongest competitor and why', 'placeholder' => 'Name and explain...' ),
                        array( 'type' => 'textarea', 'label' => 'What competitors do well', 'placeholder' => 'List competitor strengths...' ),
                        array( 'type' => 'textarea', 'label' => 'Gaps or weaknesses', 'placeholder' => 'List competitor weaknesses...' ),
                        array( 'type' => 'checkbox', 'label' => 'Competitive strategy', 'options' => array( 'Compete on lower price', 'Better value', 'Better quality', 'Faster service', 'Specialist offering', 'Better customer service', 'Convenience', 'Location', 'Branding', 'Other' ) ),
                        array( 'type' => 'textarea', 'label' => 'Indirect competitors or substitutes', 'placeholder' => 'List alternatives customers might use...' ),
                    ),
                ),

                // 12 ── Pricing and Revenue Model
                array(
                    'title'       => 'Pricing and Revenue Model',
                    'description' => 'Pricing strategy and revenue streams.',
                    'order'       => 12,
                    'questions'   => array(
                        array( 'type' => 'checkbox', 'label' => 'Pricing method', 'options' => array( 'Competitor pricing', 'Cost plus margin', 'Customer research', 'Industry standard', 'Supplier recommendation', 'Personal estimate', 'Existing prices', 'Not yet decided' ) ),
                        array( 'type' => 'checkbox', 'label' => 'Pricing position', 'options' => array( 'Budget option', 'Affordable value', 'Similar to competitors', 'Premium', 'Specialist high-value' ) ),
                        array( 'type' => 'textarea', 'label' => 'Discounts, payment plans or credit offered', 'placeholder' => 'Describe any pricing incentives...' ),
                        array( 'type' => 'text', 'label' => 'Expected average sale per customer', 'placeholder' => 'e.g. R2,500' ),
                        array( 'type' => 'heading', 'label' => 'Revenue Streams', 'help_text' => 'List all revenue streams.' ),
                        array( 'type' => 'textarea', 'label' => 'Revenue streams details', 'placeholder' => "Revenue Stream | Price/Fee | Monthly Qty | Monthly Income\nList each stream..." ),
                        array( 'type' => 'radio', 'label' => 'Revenue type', 'options' => array( 'Recurring income', 'Partly recurring', 'Once-off income', 'Depends on one major customer/contract' ) ),
                    ),
                ),

                // 13 ── Marketing and Sales Strategy
                array(
                    'title'       => 'Marketing and Sales Strategy',
                    'description' => 'How you will attract and convert customers.',
                    'order'       => 13,
                    'questions'   => array(
                        array( 'type' => 'checkbox', 'label' => 'Marketing platforms', 'options' => array( 'Facebook', 'Instagram', 'TikTok', 'LinkedIn', 'YouTube', 'Google', 'Website', 'Email', 'WhatsApp', 'Flyers', 'Radio', 'Newspapers', 'Events/markets', 'Referrals', 'Partnerships', 'Sales representatives', 'Other' ) ),
                        array( 'type' => 'text', 'label' => 'Best expected marketing channel', 'placeholder' => 'e.g. Google Ads' ),
                        array( 'type' => 'text', 'label' => 'Monthly marketing budget', 'placeholder' => 'e.g. R5,000' ),
                        array( 'type' => 'radio', 'label' => 'Paid advertising planned?', 'options' => array( 'Paid advertising planned', 'No paid advertising', 'Possibly' ) ),
                        array( 'type' => 'checkbox', 'label' => 'Content types', 'options' => array( 'Product ads', 'Educational content', 'Videos', 'Testimonials', 'Customer success stories', 'Promotions', 'Before-and-after results', 'Other' ) ),
                        array( 'type' => 'textarea', 'label' => 'Launch promotion or introductory offer', 'placeholder' => 'Describe any launch offers...' ),
                        array( 'type' => 'checkbox', 'label' => 'Sales channels', 'options' => array( 'Physical premises', 'Website', 'Online store', 'WhatsApp', 'Social media', 'Sales representative', 'Telephone', 'Customer premises', 'Retail partners', 'Other' ) ),
                        array( 'type' => 'textarea', 'label' => 'Describe the sales process from enquiry to payment', 'placeholder' => 'Step-by-step sales process...' ),
                        array( 'type' => 'checkbox', 'label' => 'Who handles sales?', 'options' => array( 'Owner handles sales', 'Employee', 'Sales representative', 'External agent', 'Automated system' ) ),
                        array( 'type' => 'checkbox', 'label' => 'Payment methods accepted', 'options' => array( 'Cash', 'EFT', 'Card', 'Online payment', 'Debit order', 'Credit', 'Other' ) ),
                    ),
                ),

                // 14 ── Customer Service and Retention
                array(
                    'title'       => 'Customer Service and Retention',
                    'description' => 'How you will serve and retain customers.',
                    'order'       => 14,
                    'questions'   => array(
                        array( 'type' => 'textarea', 'label' => 'Customer service approach', 'placeholder' => 'Describe your customer service philosophy...' ),
                        array( 'type' => 'textarea', 'label' => 'Guarantees, warranties or refund policies', 'placeholder' => 'Describe any guarantees or policies...' ),
                        array( 'type' => 'textarea', 'label' => 'Complaint handling process', 'placeholder' => 'How will complaints be handled?' ),
                        array( 'type' => 'checkbox', 'label' => 'Customer retention methods', 'options' => array( 'Follow-up messages', 'Email marketing', 'WhatsApp communication', 'Loyalty programme', 'Subscriptions', 'Discounts', 'Referral rewards', 'Excellent service', 'Other' ) ),
                        array( 'type' => 'radio', 'label' => 'Customer database maintained?', 'options' => array( 'Customer database maintained', 'No customer database' ) ),
                    ),
                ),

                // 15 ── Location, Premises and Operations
                array(
                    'title'       => 'Location, Premises and Operations',
                    'description' => 'Physical location and daily operations.',
                    'order'       => 15,
                    'questions'   => array(
                        array( 'type' => 'checkbox', 'label' => 'Business premises type', 'options' => array( 'Home-based', 'Rented shop', 'Office', 'Workshop', 'Factory', 'Warehouse', 'Shared workspace', 'Mobile unit', 'Customer premises', 'Online only', 'Other' ) ),
                        array( 'type' => 'radio', 'label' => 'Location status', 'options' => array( 'Location secured', 'Still looking', 'Not required' ) ),
                        array( 'type' => 'textarea', 'label' => 'Why is this location suitable?', 'placeholder' => 'Explain location advantages...' ),
                        array( 'type' => 'text', 'label' => 'Monthly rent and deposit', 'placeholder' => 'e.g. Rent: R8,000 | Deposit: R16,000' ),
                        array( 'type' => 'textarea', 'label' => 'Renovations required and estimated cost', 'placeholder' => 'Describe renovations needed...' ),
                        array( 'type' => 'textarea', 'label' => 'Describe daily operations', 'required' => true, 'placeholder' => 'Walk through a typical day...' ),
                        array( 'type' => 'text', 'label' => 'Operating hours', 'placeholder' => 'e.g. Mon-Fri 08:00-17:00, Sat 09:00-13:00' ),
                        array( 'type' => 'textarea', 'label' => 'Main steps in delivering the offering', 'placeholder' => 'List the key steps from order to delivery...' ),
                        array( 'type' => 'text', 'label' => 'Expected capacity per day/week/month', 'placeholder' => 'e.g. 20 customers per day' ),
                        array( 'type' => 'textarea', 'label' => 'Activities completed internally', 'placeholder' => 'List activities done in-house...' ),
                        array( 'type' => 'textarea', 'label' => 'Activities outsourced', 'placeholder' => 'List outsourced activities...' ),
                        array( 'type' => 'radio', 'label' => 'Operating procedures documented?', 'options' => array( 'Operating procedures available', 'Partly available', 'Not available' ) ),
                    ),
                ),

                // 16 ── Equipment, Technology, Suppliers and Stock
                array(
                    'title'       => 'Equipment, Technology, Suppliers and Stock',
                    'description' => 'Equipment, technology requirements and supply chain.',
                    'order'       => 16,
                    'questions'   => array(
                        array( 'type' => 'heading', 'label' => 'Equipment and Assets', 'help_text' => 'List equipment and assets needed.' ),
                        array( 'type' => 'textarea', 'label' => 'Equipment/assets needed', 'placeholder' => "Equipment/Asset | Quantity | Cost Each | Total\nList items..." ),
                        array( 'type' => 'textarea', 'label' => 'Assets already owned', 'placeholder' => 'List assets you already have...' ),
                        array( 'type' => 'textarea', 'label' => 'Equipment to be leased or financed', 'placeholder' => 'List items to lease or finance...' ),
                        array( 'type' => 'textarea', 'label' => 'Vehicle requirement', 'placeholder' => 'Describe vehicle needs...' ),
                        array( 'type' => 'checkbox', 'label' => 'Technology and systems needed', 'options' => array( 'Computer/laptop', 'Mobile phone', 'Printer', 'Internet', 'Point-of-sale', 'Card machine', 'Accounting software', 'Booking system', 'Customer database', 'Website', 'Online store', 'Cloud storage', 'Other' ) ),
                        array( 'type' => 'heading', 'label' => 'Technology and Systems', 'help_text' => 'List technology costs.' ),
                        array( 'type' => 'textarea', 'label' => 'Technology costs', 'placeholder' => "Technology/System | Once-off Cost | Monthly Cost\nList items..." ),
                        array( 'type' => 'heading', 'label' => 'Suppliers', 'help_text' => 'List all suppliers.' ),
                        array( 'type' => 'textarea', 'label' => 'Supplier details', 'placeholder' => "Supplier | Product/Service | Location | Payment Terms\nList suppliers..." ),
                        array( 'type' => 'radio', 'label' => 'Supply chain risk', 'options' => array( 'Depends on one supplier', 'Backup suppliers available' ) ),
                        array( 'type' => 'checkbox', 'label' => 'Stock requirements', 'options' => array( 'Stock required', 'Products/materials imported' ) ),
                        array( 'type' => 'textarea', 'label' => 'Opening stock cost and purchase frequency', 'placeholder' => 'Describe stock requirements...' ),
                    ),
                ),

                // 17 ── Management, Staffing and Organisation
                array(
                    'title'       => 'Management, Staffing and Organisation',
                    'description' => 'Management team, staffing and organisational structure.',
                    'order'       => 17,
                    'questions'   => array(
                        array( 'type' => 'text', 'label' => 'Who will manage the business?', 'required' => true, 'placeholder' => 'e.g. John Doe' ),
                        array( 'type' => 'textarea', 'label' => 'Owner/management experience and qualifications', 'required' => true, 'placeholder' => 'List relevant experience and qualifications...' ),
                        array( 'type' => 'heading', 'label' => 'Staffing', 'help_text' => 'List planned staffing requirements.' ),
                        array( 'type' => 'textarea', 'label' => 'Staffing details', 'placeholder' => "Position | Number | Monthly Salary | Start Date\nList positions..." ),
                        array( 'type' => 'textarea', 'label' => 'Staff training required', 'placeholder' => 'Describe training needs...' ),
                        array( 'type' => 'textarea', 'label' => 'Missing skills', 'placeholder' => 'List skills gaps to address...' ),
                        array( 'type' => 'radio', 'label' => 'Can business operate without owner?', 'options' => array( 'Business can operate without owner', 'Partly', 'No' ) ),
                        array( 'type' => 'heading', 'label' => 'Key Roles', 'help_text' => 'Define key roles and responsibilities.' ),
                        array( 'type' => 'textarea', 'label' => 'Roles details', 'placeholder' => "Role | Person Responsible | Main Responsibilities\nList roles..." ),
                        array( 'type' => 'checkbox', 'label' => 'Professional advisors', 'options' => array( 'Accountant', 'Bookkeeper', 'Tax practitioner', 'Attorney', 'Business consultant', 'Marketing specialist', 'IT support', 'Health and safety consultant', 'Other' ) ),
                    ),
                ),

                // 18 ── Startup Costs and Monthly Expenses
                array(
                    'title'       => 'Startup Costs and Monthly Expenses',
                    'description' => 'Financial requirements for starting and running the business.',
                    'order'       => 18,
                    'questions'   => array(
                        array( 'type' => 'heading', 'label' => 'Startup Costs', 'help_text' => 'List all one-time startup costs.' ),
                        array( 'type' => 'textarea', 'label' => 'Startup costs', 'required' => true, 'placeholder' => "Startup Cost | Estimated Amount\nList all costs..." ),
                        array( 'type' => 'text', 'label' => 'Estimated total startup cost', 'required' => true, 'placeholder' => 'e.g. R150,000' ),
                        array( 'type' => 'text', 'label' => 'Startup costs already paid', 'placeholder' => 'e.g. R30,000' ),
                        array( 'type' => 'heading', 'label' => 'Monthly Expenses', 'help_text' => 'List all recurring monthly expenses.' ),
                        array( 'type' => 'textarea', 'label' => 'Monthly expenses', 'required' => true, 'placeholder' => "Monthly Expense | Estimated Amount\nList all expenses..." ),
                        array( 'type' => 'text', 'label' => 'Estimated total monthly expenses', 'required' => true, 'placeholder' => 'e.g. R45,000' ),
                    ),
                ),

                // 19 ── Sales Forecast and Funding
                array(
                    'title'       => 'Sales Forecast and Funding',
                    'description' => 'Revenue projections and funding requirements.',
                    'order'       => 19,
                    'questions'   => array(
                        array( 'type' => 'text', 'label' => 'Average customers/orders per month', 'placeholder' => 'e.g. 40' ),
                        array( 'type' => 'text', 'label' => 'Average sale per customer', 'placeholder' => 'e.g. R2,000' ),
                        array( 'type' => 'textarea', 'label' => 'Expected monthly and annual revenue', 'placeholder' => 'Describe revenue expectations...' ),
                        array( 'type' => 'heading', 'label' => 'Sales Forecast', 'help_text' => 'Provide month-by-month sales projections.' ),
                        array( 'type' => 'textarea', 'label' => 'Sales forecast', 'placeholder' => "Month | Expected Sales\nList month-by-month..." ),
                        array( 'type' => 'textarea', 'label' => 'Assumptions supporting the forecast', 'placeholder' => 'List assumptions...' ),
                        array( 'type' => 'text', 'label' => 'Owner funds available', 'placeholder' => 'e.g. R50,000' ),
                        array( 'type' => 'text', 'label' => 'External funding required', 'placeholder' => 'e.g. R100,000' ),
                        array( 'type' => 'checkbox', 'label' => 'Funding sources', 'options' => array( 'Bank loan', 'Investor funding', 'Government funding', 'Asset finance', 'Vehicle finance', 'Partner contribution', 'Family/friends', 'Personal loan', 'Other' ) ),
                        array( 'type' => 'heading', 'label' => 'Use of Funding', 'help_text' => 'Describe how funding will be allocated.' ),
                        array( 'type' => 'textarea', 'label' => 'Use of funding details', 'placeholder' => "Use of Funding | Amount\nList each use..." ),
                        array( 'type' => 'radio', 'label' => 'Funding application status', 'options' => array( 'Funding approved', 'Application in progress', 'Not yet applied' ) ),
                        array( 'type' => 'textarea', 'label' => 'Owner contribution', 'placeholder' => 'Describe owner\'s financial contribution...' ),
                    ),
                ),

                // 20 ── Loan and Financial Targets
                array(
                    'title'       => 'Loan and Financial Targets',
                    'description' => 'Loan details and financial benchmarks.',
                    'order'       => 20,
                    'questions'   => array(
                        array( 'type' => 'text', 'label' => 'Requested loan amount', 'placeholder' => 'e.g. R250,000' ),
                        array( 'type' => 'text', 'label' => 'Preferred repayment period', 'placeholder' => 'e.g. 36 months' ),
                        array( 'type' => 'text', 'label' => 'Interest rate if known', 'placeholder' => 'e.g. Prime + 2%' ),
                        array( 'type' => 'text', 'label' => 'Maximum affordable monthly repayment', 'placeholder' => 'e.g. R8,000' ),
                        array( 'type' => 'textarea', 'label' => 'Collateral/security available', 'placeholder' => 'Describe assets available as security...' ),
                        array( 'type' => 'heading', 'label' => 'Financial Targets', 'help_text' => 'Set financial targets and benchmarks.' ),
                        array( 'type' => 'textarea', 'label' => 'Financial targets', 'placeholder' => "Financial Target | Target\nList targets..." ),
                        array( 'type' => 'text', 'label' => 'Monthly profit required', 'placeholder' => 'e.g. R15,000' ),
                        array( 'type' => 'radio', 'label' => 'Expected time to profitability', 'options' => array( 'Profit needed under 1 month', '1-3 months', '4-6 months', '7-12 months', 'More than 12 months' ) ),
                    ),
                ),

                // 21 ── Risks and SWOT Analysis
                array(
                    'title'       => 'Risks and SWOT Analysis',
                    'description' => 'Risk assessment and strategic analysis.',
                    'order'       => 21,
                    'questions'   => array(
                        array( 'type' => 'heading', 'label' => 'Risks', 'help_text' => 'Identify and plan for key business risks.' ),
                        array( 'type' => 'textarea', 'label' => 'Risk details', 'placeholder' => "Risk | Possible Impact | Planned Response\nList each risk..." ),
                        array( 'type' => 'checkbox', 'label' => 'Common risks to consider', 'options' => array( 'Low sales', 'Strong competition', 'Cash-flow shortages', 'High operating costs', 'Supplier problems', 'Staff shortages', 'Equipment failure', 'Legal/compliance problems', 'Technology failure', 'Economic conditions', 'Other' ) ),
                        array( 'type' => 'textarea', 'label' => 'What could cause the business to fail?', 'placeholder' => 'Describe potential failure scenarios...' ),
                        array( 'type' => 'textarea', 'label' => 'What action will be taken if sales are lower than expected?', 'placeholder' => 'Describe contingency plans...' ),
                        array( 'type' => 'textarea', 'label' => 'Strengths', 'required' => true, 'placeholder' => 'List business strengths...' ),
                        array( 'type' => 'textarea', 'label' => 'Weaknesses', 'required' => true, 'placeholder' => 'List business weaknesses...' ),
                        array( 'type' => 'textarea', 'label' => 'Opportunities', 'required' => true, 'placeholder' => 'List market opportunities...' ),
                        array( 'type' => 'textarea', 'label' => 'Threats', 'required' => true, 'placeholder' => 'List threats to the business...' ),
                    ),
                ),

                // 22 ── Growth Strategy and Implementation
                array(
                    'title'       => 'Growth Strategy and Implementation',
                    'description' => 'Growth plans and implementation timeline.',
                    'order'       => 22,
                    'questions'   => array(
                        array( 'type' => 'checkbox', 'label' => 'Growth plans', 'options' => array( 'More customers', 'Additional offerings', 'New branches', 'New geographic areas', 'Online growth', 'Staff expansion', 'Franchising', 'Licensing', 'Partnerships', 'Exporting', 'Other' ) ),
                        array( 'type' => 'text', 'label' => 'When is expansion expected to begin?', 'placeholder' => 'e.g. Year 2' ),
                        array( 'type' => 'textarea', 'label' => 'Resources required for growth', 'placeholder' => 'List resources needed...' ),
                        array( 'type' => 'radio', 'label' => 'Franchising/licensing potential', 'options' => array( 'Can be franchised/licensed', 'Possibly', 'No' ) ),
                        array( 'type' => 'radio', 'label' => 'Can operate without owner?', 'options' => array( 'Yes', 'Possibly', 'No' ) ),
                        array( 'type' => 'heading', 'label' => 'Implementation', 'help_text' => 'Key actions and timeline.' ),
                        array( 'type' => 'textarea', 'label' => 'Implementation timeline', 'placeholder' => "Action | Person Responsible | Target Date | Status\nList actions..." ),
                    ),
                ),

                // 23 ── Business Plan Requirements
                array(
                    'title'       => 'Business Plan Requirements',
                    'description' => 'Select the sections to include in the business plan.',
                    'order'       => 23,
                    'questions'   => array(
                        array( 'type' => 'checkbox', 'label' => 'Required sections', 'options' => array( 'Executive summary', 'Business description', 'Vision, mission and objectives', 'Ownership and legal structure', 'Products and services', 'Industry analysis', 'Market analysis', 'Target customer profile', 'Competitor analysis', 'SWOT analysis', 'Marketing strategy', 'Sales strategy', 'Operations plan', 'Management and staffing', 'Organisational structure', 'Startup cost estimate', 'Sales forecast', 'Profit-and-loss forecast', 'Cash-flow forecast', 'Break-even analysis', 'Funding requirement', 'Loan repayment information', 'Risk assessment', 'Growth strategy', 'Implementation timeline', 'Supporting appendices', 'Other' ) ),
                    ),
                ),

                // 24 ── Supporting Documents and Additional Comments
                array(
                    'title'       => 'Supporting Documents and Additional Comments',
                    'description' => 'Documents available and any final comments.',
                    'order'       => 24,
                    'questions'   => array(
                        array( 'type' => 'checkbox', 'label' => 'Available supporting documents', 'options' => array( 'Company registration', 'Owner ID documents', 'Tax documents', 'Licences/permits', 'Owner/management CVs', 'Qualifications', 'Product photos', 'Product catalogue', 'Price list', 'Supplier quotations', 'Equipment quotations', 'Rental quotation/lease', 'Vehicle quotations', 'Financial statements', 'Bank statements', 'Sales records', 'Customer contracts', 'Letters of intent', 'Market research', 'Competitor information', 'Logo/branding files', 'Existing business plan', 'Funding documents', 'Other' ) ),
                        array( 'type' => 'textarea', 'label' => 'Relevant links or additional information', 'placeholder' => 'Paste any relevant URLs...' ),
                        array( 'type' => 'textarea', 'label' => 'Additional comments', 'placeholder' => 'Any other information you would like to share...' ),
                    ),
                ),
            ),
        );
    }
}

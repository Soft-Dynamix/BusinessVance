<?php
/**
 * BusinessVance Document Parser
 *
 * Parses PDF and Word (.docx) documents to extract questionnaire structure
 * (sections, questions, question types) and converts them into the plugin's
 * template data format.
 *
 * Uses only built-in PHP extensions: ZipArchive, SimpleXML, zlib.
 * No external Composer dependencies required.
 *
 * @package BusinessVance_Services_Manager
 * @since   2.7.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BV_Document_Parser {

    /**
     * Minimum confidence score (0-1) for a line to be considered a section heading.
     */
    const SECTION_HEADING_CONFIDENCE = 0.55;

    /**
     * Minimum confidence score for a line to be considered a question.
     */
    const QUESTION_CONFIDENCE = 0.4;

    /**
     * Maximum file size in bytes (10MB).
     */
    const MAX_FILE_SIZE = 10485760;

    /**
     * Parse a document file and extract questionnaire structure.
     *
     * @param string $filepath Full path to the uploaded file.
     * @param string $filename Original filename.
     * @return array {
     *     @type string $name        Template name.
     *     @type string $description Template description.
     *     @type array  $sections    Detected sections with questions.
     *     @type int    $total_sections  Total sections detected.
     *     @type int    $total_questions Total questions detected.
     *     @type string $format      File format (pdf or docx).
     * }
     * @throws \Exception If parsing fails.
     */
    public function parse_file( $filepath, $filename ) {
        $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

        if ( ! file_exists( $filepath ) ) {
            throw new \Exception( 'Uploaded file not found.' );
        }

        if ( filesize( $filepath ) > self::MAX_FILE_SIZE ) {
            throw new \Exception( 'File is too large. Maximum size is 10MB.' );
        }

        $lines = array();

        if ( $ext === 'docx' || $ext === 'doc' ) {
            if ( $ext === 'doc' ) {
                throw new \Exception( 'Only .docx files are supported (not .doc). Please save your document as .docx in Word.' );
            }
            $lines = $this->parse_docx( $filepath );
        } elseif ( $ext === 'pdf' ) {
            $lines = $this->parse_pdf( $filepath );
        } else {
            throw new \Exception( 'Unsupported file format. Please upload a PDF or .docx file.' );
        }

        if ( empty( $lines ) ) {
            throw new \Exception( 'Could not extract any text from the document. The file may be empty, image-based, or corrupted.' );
        }

        // Extract questionnaire structure from lines
        $structure = $this->extract_structure( $lines, $filename );

        $structure['format']          = ( $ext === 'docx' ) ? 'docx' : 'pdf';
        $structure['total_sections']  = count( $structure['sections'] );
        $structure['total_questions'] = 0;
        foreach ( $structure['sections'] as $section ) {
            $structure['total_questions'] += count( $section['questions'] );
        }

        return $structure;
    }

    /* =========================================================================
     * PDF Text Extraction
     * ====================================================================== */

    /**
     * Extract text lines from a PDF file.
     *
     * Parses PDF objects to detect filter chains (e.g., ASCII85Decode + FlateDecode),
     * applies them in order, then extracts text using Tj/TJ operators.
     * Handles most common PDF formats (non-encrypted, standard text encoding).
     *
     * @param string $filepath Path to PDF file.
     * @return array Array of extracted text lines.
     */
    private function parse_pdf( $filepath ) {
        $content = file_get_contents( $filepath );

        if ( $content === false ) {
            return array();
        }

        $streams = $this->extract_pdf_streams( $content );

        if ( empty( $streams ) ) {
            // Last resort: try raw content
            $streams[] = $content;
        }

        $lines = array();
        foreach ( $streams as $stream ) {
            $text = $this->extract_pdf_text_from_stream( $stream );
            if ( ! empty( $text ) ) {
                $lines = array_merge( $lines, $this->split_into_lines( $text ) );
            }
        }

        return $this->clean_lines( $lines );
    }

    /**
     * Extract and decode all PDF content streams from raw file content.
     *
     * Finds each object with a stream, reads its /Filter dictionary entry,
     * and applies the filter chain to produce decompressed content.
     *
     * Supported filters: FlateDecode, ASCII85Decode, ASCIIHexDecode.
     *
     * @param string $content Raw PDF file content.
     * @return array Array of decompressed stream strings.
     */
    private function extract_pdf_streams( $content ) {
        $decoded_streams = array();

        // Match each indirect object that contains a stream
        // Pattern: N M obj ... << ... /Filter ... >> stream\r?\n ... endstream ... endobj
        if ( ! preg_match_all( '/(\d+)\s+\d+\s+obj\s*(.*?)endobj/s', $content, $obj_matches ) ) {
            // Fallback: try simple stream/endstream without object context
            return $this->extract_pdf_streams_simple( $content );
        }

        foreach ( $obj_matches[2] as $obj_body ) {
            // Check if this object has a stream
            if ( ! preg_match( '/stream\r?\n(.*?)\r?\nendstream/s', $obj_body, $stream_match ) ) {
                if ( ! preg_match( '/stream\r?\n(.*?)endstream/s', $obj_body, $stream_match ) ) {
                    continue;
                }
            }

            $raw_data = $stream_match[1];

            // Detect filter(s)
            $filters = array();

            // Array of filters: /Filter [ /ASCII85Decode /FlateDecode ]
            if ( preg_match( '/\/Filter\s*\[\s*(.*?)\s*\]/s', $obj_body, $filter_array_match ) ) {
                $filter_str = $filter_array_match[1];
                preg_match_all( '/\/(\w+)/', $filter_str, $filter_names );
                $filters = $filter_names[1];
            }
            // Single filter: /Filter /FlateDecode
            elseif ( preg_match( '/\/Filter\s*\/(\w+)/', $obj_body, $filter_single_match ) ) {
                $filters = array( $filter_single_match[1] );
            }

            // Skip image objects — they don't contain text operators
            if ( in_array( 'Image', $filters, true ) ) {
                continue;
            }
            if ( preg_match( '/\/Subtype\s*\/Image/', $obj_body ) ) {
                continue;
            }

            // Apply filter chain
            $data = trim( $raw_data );

            if ( empty( $filters ) ) {
                // No filter — raw data (might be plain text or uncompressed)
                $decoded_streams[] = $data;
                continue;
            }

            foreach ( $filters as $filter ) {
                $data = $this->apply_pdf_filter( $data, $filter );
                if ( $data === false ) {
                    break;
                }
            }

            if ( $data !== false && ! empty( $data ) ) {
                $decoded_streams[] = $data;
            }
        }

        return $decoded_streams;
    }

    /**
     * Fallback: extract PDF streams using simple regex (no filter detection).
     *
     * @param string $content Raw PDF file content.
     * @return array Array of decompressed stream strings.
     */
    private function extract_pdf_streams_simple( $content ) {
        $streams = array();

        // Try FlateDecode (zlib) directly
        if ( preg_match_all( '/stream\s*\r?\n(.*?)\r?\nendstream/s', $content, $matches ) ) {
            foreach ( $matches[1] as $stream_data ) {
                $decompressed = @gzuncompress( $stream_data );
                if ( $decompressed !== false ) {
                    $streams[] = $decompressed;
                }
            }
        }

        // Also try without leading newline before endstream
        if ( empty( $streams ) && preg_match_all( '/stream\r?\n(.*?)endstream/s', $content, $matches ) ) {
            foreach ( $matches[1] as $stream_data ) {
                $stream_data = trim( $stream_data );
                $decompressed = @gzuncompress( $stream_data );
                if ( $decompressed !== false ) {
                    $streams[] = $decompressed;
                }
            }
        }

        return $streams;
    }

    /**
     * Apply a single PDF filter to data.
     *
     * @param string $data   Input data.
     * @param string $filter Filter name (e.g., FlateDecode, ASCII85Decode).
     * @return string|false Decoded data, or false on failure.
     */
    private function apply_pdf_filter( $data, $filter ) {
        switch ( $filter ) {
            case 'FlateDecode':
                // Try zlib format first (gzuncompress)
                $result = @gzuncompress( $data );
                if ( $result !== false ) {
                    return $result;
                }
                // Try raw deflate (gzinflate)
                $result = @gzinflate( $data );
                if ( $result !== false ) {
                    return $result;
                }
                // Try with max length (strip possible checksum)
                $result = @gzinflate( substr( $data, 2, -4 ) );
                if ( $result !== false ) {
                    return $result;
                }
                return false;

            case 'ASCII85Decode':
                return $this->decode_ascii85( $data );

            case 'ASCIIHexDecode':
                return $this->decode_ascii_hex( $data );

            default:
                // Unknown filter — return data as-is
                return $data;
        }
    }

    /**
     * Decode ASCII85 encoded data.
     *
     * Handles the standard Base85 encoding used in PDFs.
     * Supports the 'z' shorthand for all-zero groups and EOD marker '~>'.
     *
     * @param string $data ASCII85 encoded string.
     * @return string|false Decoded binary data, or false on failure.
     */
    private function decode_ascii85( $data ) {
        // Remove whitespace and EOD marker
        $data = preg_replace( '/\s/', '', $data );
        $data = str_replace( '~>', '', $data );
        $data = trim( $data );

        if ( $data === '' ) {
            return '';
        }

        $result = '';
        $len = strlen( $data );

        // Pad to a multiple of 5
        $padding = ( 5 - ( $len % 5 ) ) % 5;
        $data .= str_repeat( 'u', $padding );

        for ( $i = 0; $i < strlen( $data ); $i += 5 ) {
            $chunk = substr( $data, $i, 5 );

            // 'z' is shorthand for a group of 4 zero bytes
            if ( $chunk === 'z' ) {
                $result .= "\x00\x00\x00\x00";
                continue;
            }

            // Decode 5 ASCII85 chars to 4 bytes
            $num = 0;
            for ( $j = 0; $j < 5; $j++ ) {
                $char = ord( $chunk[ $j ] );
                if ( $char < 33 || $char > 117 ) {
                    return false; // Invalid character
                }
                $num = $num * 85 + ( $char - 33 );
            }

            $result .= pack( 'N', $num );
        }

        // Remove padding bytes
        if ( $padding > 0 ) {
            $result = substr( $result, 0, strlen( $result ) - $padding );
        }

        return $result;
    }

    /**
     * Decode ASCIIHex encoded data.
     *
     * Handles the ASCIIHexDecode filter used in some PDFs.
     *
     * @param string $data ASCIIHex encoded string.
     * @return string Decoded binary data.
     */
    private function decode_ascii_hex( $data ) {
        // Remove whitespace and EOD marker '>'
        $data = preg_replace( '/\s/', '', $data );
        $data = rtrim( $data, '>' );

        if ( strlen( $data ) % 2 !== 0 ) {
            $data .= '0'; // Pad odd-length hex string
        }

        return pack( 'H*', $data );
    }

    /**
     * Extract text from a PDF content stream.
     *
     * @param string $stream Decompressed PDF stream content.
     * @return string Extracted text.
     */
    private function extract_pdf_text_from_stream( $stream ) {
        $text = '';
        $font_sizes = array();
        $current_font_size = 12;

        // Extract font size declarations: /F1 12 Tf or similar
        preg_match_all( '/\/(\w+)\s+([\d.]+)\s+Tf/', $stream, $font_matches, PREG_SET_ORDER );
        foreach ( $font_matches as $fm ) {
            $font_sizes[ '/' . $fm[1] ] = (float) $fm[2];
        }

        // Extract text from TJ arrays (most common in modern PDFs)
        // TJ format: [(text1) -N (text2)] TJ
        preg_match_all( '/\[((?:\([^)]*\)|[^\])\[\]])*?)\]\s*TJ/s', $stream, $tj_matches );
        foreach ( $tj_matches[1] as $tj_block ) {
            $segment_text = '';
            // Extract individual text segments from TJ array
            preg_match_all( '/\(([^)]*)\)/', $tj_block, $seg_matches );
            foreach ( $seg_matches[1] as $segment ) {
                // Decode PDF string encoding
                $segment_text .= $this->decode_pdf_string( $segment );
            }

            if ( trim( $segment_text ) !== '' ) {
                // Check for font size change before this TJ
                $pre_context = substr( $stream, 0, strpos( $stream, $tj_block ) );
                if ( preg_match( '/\/\w+\s+([\d.]+)\s+Tf/', $pre_context, $fs_match ) ) {
                    $current_font_size = (float) $fs_match[1];
                }
                $text .= $segment_text . ' ';
            }
        }

        // Extract text from Tj operator (simple text showing)
        preg_match_all( '/\(([^)]*)\)\s*Tj/', $stream, $tj_simple_matches );
        foreach ( $tj_simple_matches[1] as $segment ) {
            $decoded = $this->decode_pdf_string( $segment );
            if ( trim( $decoded ) !== '' ) {
                $text .= $decoded . ' ';
            }
        }

        // Extract text from ' operator (move to next line + show)
        preg_match_all( '/\(([^)]*)\)\s*\'/', $stream, $quote_matches );
        foreach ( $quote_matches[1] as $segment ) {
            $decoded = $this->decode_pdf_string( $segment );
            if ( trim( $decoded ) !== '' ) {
                $text .= "\n" . $decoded . ' ';
            }
        }

        // Extract text from " operator (set spacing + show)
        preg_match_all( '/\(([^)]*)\)\s*"/', $stream, $dquote_matches );
        foreach ( $dquote_matches[1] as $segment ) {
            $decoded = $this->decode_pdf_string( $segment );
            if ( trim( $decoded ) !== '' ) {
                $text .= ' ' . $decoded . ' ';
            }
        }

        // Detect line breaks from Td, TD, Tm, T* operators
        $text = preg_replace( '/\s*T[Dd*]\s*\([^)]*\)\s*TJ/s', "\n", $text );
        $text = preg_replace( '/\s*Tm\s*[\d.\s-]+\s*TJ/s', "\n", $text );
        $text = preg_replace( '/\s*ET\s*BT/s', "\n", $text );

        return $text;
    }

    /**
     * Decode PDF string encoding (handle octal and escape sequences).
     *
     * @param string $str PDF-encoded string.
     * @return string Decoded string.
     */
    private function decode_pdf_string( $str ) {
        // Decode octal sequences: \012 -> newline, etc.
        $str = preg_replace_callback( '/\\\\([0-7]{1,3})/', function( $m ) {
            return chr( octdec( $m[1] ) );
        }, $str );

        // Decode common escape sequences
        $str = str_replace( array( '\\n', '\\r', '\\t', '\\(', '\\)', '\\\\' ), array( "\n", "\r", "\t", '(', ')', '\\' ), $str );

        // Convert Latin-1 to UTF-8 if needed
        if ( function_exists( 'mb_convert_encoding' ) ) {
            $converted = @mb_convert_encoding( $str, 'UTF-8', 'ISO-8859-1' );
            if ( $converted !== false ) {
                return $converted;
            }
        }

        return $str;
    }

    /* =========================================================================
     * DOCX Text Extraction
     * ====================================================================== */

    /**
     * Extract text lines from a .docx file.
     *
     * Uses PHP's ZipArchive to read the XML content of the DOCX (which is a ZIP archive).
     *
     * @param string $filepath Path to .docx file.
     * @return array Array of extracted text lines with metadata.
     */
    private function parse_docx( $filepath ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            throw new \Exception( 'ZipArchive extension is required to parse .docx files. Please contact your hosting provider.' );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $filepath ) !== true ) {
            throw new \Exception( 'Could not open .docx file. The file may be corrupted.' );
        }

        // Read the main document XML
        $doc_xml = $zip->getFromName( 'word/document.xml' );
        if ( $doc_xml === false ) {
            $zip->close();
            throw new \Exception( 'Invalid .docx file - word/document.xml not found inside.' );
        }

        // Also read styles for bold/size detection
        $styles_xml = $zip->getFromName( 'word/styles.xml' );

        $zip->close();

        // Parse XML - suppress warnings for malformed XML
        $doc = @simplexml_load_string( $doc_xml );
        if ( $doc === false ) {
            throw new \Exception( 'Could not parse the document XML structure.' );
        }

        // Register Word XML namespace
        $namespaces = $doc->getNamespaces( true );
        $w_ns = '';
        foreach ( $namespaces as $prefix => $uri ) {
            if ( strpos( $uri, 'wordprocessingml' ) !== false || strpos( $uri, 'openxmlformats' ) !== false ) {
                $w_ns = $prefix;
                break;
            }
        }

        $lines = array();
        $this->extract_docx_paragraphs( $doc, $w_ns, $lines );

        return $this->clean_lines( $lines );
    }

    /**
     * Recursively extract paragraphs from DOCX XML.
     *
     * @param \SimpleXMLElement $node  XML node.
     * @param string           $w_ns  Word namespace prefix.
     * @param array            &$lines Accumulated lines.
     * @param int              $depth Current depth for nested elements.
     */
    private function extract_docx_paragraphs( $node, $w_ns, &$lines, $depth = 0 ) {
        $prefix = $w_ns ? $w_ns . ':' : '';

        // Look for paragraph elements
        foreach ( $node->children() as $child ) {
            $local_name = $child->getName();

            if ( $local_name === 'body' || $local_name === 'sectPr' || $local_name === 'document' ) {
                $this->extract_docx_paragraphs( $child, $w_ns, $lines, $depth );
                continue;
            }

            if ( $local_name === 'p' ) {
                $para_text = '';
                $is_bold = false;
                $font_size = 0;
                $style_name = '';

                // Check paragraph style
                $p_pr = $child->children( $w_ns ? $namespaces[$w_ns] : null )->pPr;
                if ( ! $p_pr ) {
                    // Try with no namespace
                    foreach ( $child->children() as $p_child ) {
                        if ( $p_child->getName() === 'pPr' ) {
                            $p_pr = $p_child;
                            break;
                        }
                    }
                }

                // Try to extract paragraph properties
                $p_pr_children = $this->get_xml_children( $child, $prefix . 'pPr' );
                if ( $p_pr_children ) {
                    $pstyle = $this->get_xml_children( $p_pr_children, $prefix . 'pStyle' );
                    if ( $pstyle && isset( $pstyle['val'] ) ) {
                        $style_name = strtolower( (string) $pstyle['val'] );
                    }
                }

                // Extract text from all runs in the paragraph
                $runs = $this->find_all_elements( $child, $prefix . 'r' );
                foreach ( $runs as $run ) {
                    $run_text = '';
                    $run_bold = false;

                    // Check run properties for bold
                    $r_pr = $this->get_xml_children( $run, $prefix . 'rPr' );
                    if ( $r_pr ) {
                        $b_elem = $this->get_xml_children( $r_pr, $prefix . 'b' );
                        if ( $b_elem ) {
                            $val = (string) $b_elem['val'];
                            $run_bold = ( $val !== '0' && $val !== 'false' );
                            $is_bold = $run_bold || $is_bold;
                        }
                        // Check font size (in half-points)
                        $sz_elem = $this->get_xml_children( $r_pr, $prefix . 'sz' );
                        if ( $sz_elem ) {
                            $font_size = max( $font_size, (int) (string) $sz_elem['val'] );
                        }
                    }

                    // Get text content from t element
                    $t_elements = $this->find_all_elements( $run, $prefix . 't' );
                    foreach ( $t_elements as $t ) {
                        $run_text .= (string) $t;
                    }

                    // Handle line breaks within a run
                    $br_elements = $this->find_all_elements( $run, $prefix . 'br' );
                    if ( count( $br_elements ) > 0 && ! empty( $run_text ) ) {
                        $run_text .= "\n";
                    }

                    if ( trim( $run_text ) !== '' ) {
                        $para_text .= $run_text;
                    }
                }

                $para_text = trim( $para_text );
                if ( $para_text !== '' ) {
                    // Build metadata about this line
                    $meta = array();
                    if ( $is_bold ) {
                        $meta['bold'] = true;
                    }
                    if ( $font_size > 0 ) {
                        $meta['font_size'] = $font_size; // in half-points (e.g., 24 = 12pt)
                    }
                    if ( ! empty( $style_name ) ) {
                        $meta['style'] = $style_name;
                    }

                    // Detect heading styles
                    if ( preg_match( '/heading\s*(\d+)/i', $style_name, $hm ) ) {
                        $meta['heading_level'] = (int) $hm[1];
                    }

                    $lines[] = array(
                        'text'  => $para_text,
                        'meta'  => $meta,
                    );
                }
            } else {
                // Recurse into other container elements
                $this->extract_docx_paragraphs( $child, $w_ns, $lines, $depth + 1 );
            }
        }
    }

    /**
     * Get direct child element by tag name.
     *
     * @param \SimpleXMLElement $parent Parent element.
     * @param string           $tag    Tag name (with namespace prefix if needed).
     * @return \SimpleXMLElement|null Child element or null.
     */
    private function get_xml_children( $parent, $tag ) {
        foreach ( $parent->children() as $child ) {
            if ( $child->getName() === $tag || $child->getName() === str_replace( 'w:', '', $tag ) ) {
                return $child;
            }
            // Strip namespace prefix for comparison
            $child_name = $child->getName();
            $tag_local  = preg_replace( '/^.*:/', '', $tag );
            if ( $child_name === $tag_local ) {
                return $child;
            }
        }
        return null;
    }

    /**
     * Find all descendant elements matching a tag name.
     *
     * @param \SimpleXMLElement $node Parent element.
     * @param string           $tag  Tag name to find.
     * @return array Array of matching SimpleXMLElement objects.
     */
    private function find_all_elements( $node, $tag ) {
        $results = array();
        $tag_local = preg_replace( '/^.*:/', '', $tag );

        foreach ( $node->xpath( '//' . $tag ) as $found ) {
            $results[] = $found;
        }

        // Fallback: try without namespace
        if ( empty( $results ) && strpos( $tag, ':' ) !== false ) {
            foreach ( $node->xpath( '//' . $tag_local ) as $found ) {
                $results[] = $found;
            }
        }

        // Manual fallback if xpath fails
        if ( empty( $results ) ) {
            $this->find_elements_recursive( $node, $tag_local, $results );
        }

        return $results;
    }

    /**
     * Recursively find elements by tag name.
     */
    private function find_elements_recursive( $node, $tag, &$results ) {
        foreach ( $node->children() as $child ) {
            if ( $child->getName() === $tag ) {
                $results[] = $child;
            }
            $this->find_elements_recursive( $child, $tag, $results );
        }
    }

    /* =========================================================================
     * Structure Extraction (Heuristics)
     * ====================================================================== */

    /**
     * Extract questionnaire structure from parsed text lines.
     *
     * @param array  $lines    Array of text lines (strings or arrays with text+meta).
     * @param string $filename Original filename (used for template name fallback).
     * @return array {
     *     @type string $name        Template name.
     *     @type string $description Description.
     *     @type array  $sections    Sections with questions.
     * }
     */
    private function extract_structure( $lines, $filename ) {
        // Normalize lines to text + meta format
        $normalized = array();
        foreach ( $lines as $line ) {
            if ( is_array( $line ) ) {
                $normalized[] = array(
                    'text' => isset( $line['text'] ) ? $line['text'] : '',
                    'meta' => isset( $line['meta'] ) ? $line['meta'] : array(),
                );
            } else {
                $normalized[] = array(
                    'text' => $line,
                    'meta' => array(),
                );
            }
        }

        // Filter out empty lines
        $normalized = array_filter( $normalized, function( $l ) {
            return trim( $l['text'] ) !== '';
        });
        $normalized = array_values( $normalized );

        if ( empty( $normalized ) ) {
            return array(
                'name'        => $this->filename_to_name( $filename ),
                'description' => 'Imported from ' . $filename,
                'sections'    => array(),
            );
        }

        // Extract template name from first heading or use filename
        $template_name = $this->detect_template_name( $normalized );
        $template_desc = 'Imported from ' . $filename;

        // First pass: identify section boundaries
        $section_indices = $this->detect_sections( $normalized );

        // Second pass: extract questions within each section
        $sections = array();
        foreach ( $section_indices as $sec_info ) {
            $section_lines = array_slice( $normalized, $sec_info['start'], $sec_info['end'] - $sec_info['start'] );

            $questions = $this->detect_questions( $section_lines );

            $sections[] = array(
                'title'       => $sec_info['title'],
                'description' => $sec_info['description'],
                'questions'   => $questions,
            );
        }

        // If no sections detected, treat entire document as one section
        if ( empty( $sections ) ) {
            $questions = $this->detect_questions( $normalized );
            $sections[] = array(
                'title'       => $template_name,
                'description' => $template_desc,
                'questions'   => $questions,
            );
        }

        return array(
            'name'        => $template_name,
            'description' => $template_desc,
            'sections'    => $sections,
        );
    }

    /**
     * Detect template name from the document.
     *
     * @param array $normalized Normalized lines.
     * @return string Template name.
     */
    private function detect_template_name( $normalized ) {
        // Look at first few lines for a likely title
        for ( $i = 0; $i < min( 5, count( $normalized ) ); $i++ ) {
            $line = $normalized[ $i ];
            $text = trim( $line['text'] );
            $meta = $line['meta'];

            // Skip very short lines (likely artifacts)
            if ( strlen( $text ) < 3 ) {
                continue;
            }

            // If it's a heading or bold, it's likely the title
            if ( ! empty( $meta['heading_level'] ) && $meta['heading_level'] <= 2 ) {
                return $text;
            }

            // If it's bold and reasonable length, likely a title
            if ( ! empty( $meta['bold'] ) && strlen( $text ) >= 5 && strlen( $text ) <= 120 ) {
                return $text;
            }

            // Large font size
            if ( ! empty( $meta['font_size'] ) && $meta['font_size'] >= 28 && strlen( $text ) >= 5 ) {
                return $text;
            }
        }

        // Fallback: use first meaningful line
        if ( ! empty( $normalized ) ) {
            $first = trim( $normalized[0]['text'] );
            if ( strlen( $first ) >= 3 && strlen( $first ) <= 120 ) {
                return $first;
            }
        }

        return 'Imported Questionnaire';
    }

    /**
     * Detect section boundaries in the document.
     *
     * @param array $normalized Normalized lines array.
     * @return array Array of section info arrays with start, end, title, description.
     */
    private function detect_sections( $normalized ) {
        $total = count( $normalized );
        $section_starts = array();

        for ( $i = 0; $i < $total; $i++ ) {
            $line = $normalized[ $i ];
            $text = trim( $line['text'] );
            $meta = $line['meta'];

            $score = $this->calculate_section_heading_score( $text, $meta, $normalized, $i );

            if ( $score >= self::SECTION_HEADING_CONFIDENCE ) {
                $section_starts[] = array(
                    'index' => $i,
                    'title' => $text,
                    'score' => $score,
                );
            }
        }

        // Filter: skip section starts that are too close together (keep the stronger one)
        $filtered = array();
        $last_kept = -5;
        foreach ( $section_starts as $ss ) {
            if ( $ss['index'] - $last_kept < 2 ) {
                // Too close - keep the one with higher score
                if ( ! empty( $filtered ) ) {
                    $last = end( $filtered );
                    if ( $ss['score'] > $last['score'] ) {
                        array_pop( $filtered );
                        $filtered[] = $ss;
                        $last_kept = $ss['index'];
                    }
                }
            } else {
                $filtered[] = $ss;
                $last_kept = $ss['index'];
            }
        }

        // If we found the first heading within the first 5 lines, it's likely the title,
        // not a section. Skip it as a section heading but we still use it as the template name.
        if ( ! empty( $filtered ) && $filtered[0]['index'] < 3 ) {
            array_shift( $filtered );
        }

        // Build section ranges
        $sections = array();
        for ( $i = 0; $i < count( $filtered ); $i++ ) {
            $start = $filtered[ $i ]['index'];
            $end   = ( $i + 1 < count( $filtered ) ) ? $filtered[ $i + 1 ]['index'] : $total;

            // Get description from first line after heading if it's descriptive
            $description = '';
            if ( $start + 1 < $end ) {
                $next_text = trim( $normalized[ $start + 1 ]['text'] );
                if ( strlen( $next_text ) > 20 && ! preg_match( '/[?？]$/u', $next_text )
                     && ! preg_match( '/^[\d.]+[\).]/', $next_text )
                     && ! preg_match( '/^(please|provide|enter|list|describe|state|give|write)/i', $next_text ) ) {
                    $description = $next_text;
                }
            }

            $sections[] = array(
                'start'       => $start,
                'end'         => $end,
                'title'       => $this->clean_section_title( $filtered[ $i ]['title'] ),
                'description' => $description,
            );
        }

        return $sections;
    }

    /**
     * Calculate a score (0-1) for how likely a line is a section heading.
     *
     * @param string $text    Line text.
     * @param array  $meta    Line metadata.
     * @param array  $lines   All normalized lines.
     * @param int    $index   Current line index.
     * @return float Score between 0 and 1.
     */
    private function calculate_section_heading_score( $text, $meta, $lines, $index ) {
        $score = 0;
        $len = strlen( $text );

        // Must be at least 2 chars and at most 150 chars
        if ( $len < 2 || $len > 150 ) {
            return 0;
        }

        // Must NOT end with a question mark (those are questions)
        if ( preg_match( '/[?？]\s*$/u', $text ) ) {
            return 0;
        }

        // Must NOT be a numbered question like "2.1 Briefly describe..."
        // But allow numbered section headings like "1. Client Information"
        if ( preg_match( '/^\s*(\d+[\.\)]\s+|Q\d+[\.\)]?\s+)/', $text ) ) {
            // Allow short numbered headings without question mark (e.g., "1. Client Information")
            if ( ! ( preg_match( '/^\s*\d+[\.\)]\s+[A-Z][A-Za-z]/', $text )
                   && ! preg_match( '/[?？]\s*$/u', $text )
                   && ! preg_match( '/^(please|provide|enter|list|describe|state|give|write|briefly|what|which|who|how|why|when|where|are|is|do|does|have|has|can|could|should|would|will)\b/i', $text ) ) ) {
                return 0;
            }
        }

        // --- Positive signals ---

        // Explicit heading styles in DOCX
        if ( ! empty( $meta['heading_level'] ) ) {
            $score += 0.5;
            if ( $meta['heading_level'] <= 2 ) {
                $score += 0.2;
            }
        }

        // Bold text in DOCX
        if ( ! empty( $meta['bold'] ) ) {
            $score += 0.25;
        }

        // Large font size (in half-points)
        if ( ! empty( $meta['font_size'] ) ) {
            if ( $meta['font_size'] >= 32 ) {
                $score += 0.35; // 16pt+
            } elseif ( $meta['font_size'] >= 26 ) {
                $score += 0.2;  // 13pt+
            } elseif ( $meta['font_size'] >= 22 ) {
                $score += 0.1;  // 11pt+
            }
        }

        // ALL CAPS text (50%+ uppercase)
        $upper_ratio = $this->uppercase_ratio( $text );
        if ( $upper_ratio > 0.7 && $len >= 5 ) {
            $score += 0.35;
        } elseif ( $upper_ratio > 0.5 && $len >= 5 ) {
            $score += 0.15;
        }

        // Section-like keywords
        if ( preg_match( '/^(section|part|chapter|module)\s+[\divx]+[\.\):]?/i', $text ) ) {
            $score += 0.4;
        }

        // Ends with a colon
        if ( preg_match( '/:\s*$/', $text ) ) {
            $score += 0.15;
        }

        // Short line (headings tend to be short)
        if ( $len <= 60 && $len >= 5 ) {
            $score += 0.1;
        }

        // Preceded by a blank line (gap before heading is common)
        if ( $index > 0 && trim( $lines[ $index - 1 ]['text'] ) === '' ) {
            $score += 0.1;
        }

        // Followed by a blank line (gap after heading is common)
        if ( $index + 1 < count( $lines ) && trim( $lines[ $index + 1 ]['text'] ) === '' ) {
            $score += 0.1;
        }

        // Numbered heading pattern like "1. Company Info" (short, no question mark)
        if ( preg_match( '/^\s*\d+[\.\)]\s+[A-Z]/', $text ) && $len <= 80 ) {
            $score += 0.25;
        }

        // Roman numeral heading like "I. Executive Summary"
        if ( preg_match( '/^[IVX]+\.\s+[A-Z]/', $text ) && $len <= 80 ) {
            $score += 0.3;
        }

        return min( $score, 1.0 );
    }

    /**
     * Detect questions within a section's lines.
     *
     * @param array $section_lines Lines within a section.
     * @return array Array of question data arrays.
     */
    private function detect_questions( $section_lines ) {
        $questions = array();
        $total = count( $section_lines );
        $i = 0;

        while ( $i < $total ) {
            $line = $section_lines[ $i ];
            $text = trim( $line['text'] );
            $meta = $line['meta'];

            // Skip empty lines
            if ( $text === '' ) {
                $i++;
                continue;
            }

            // Skip the section title itself (usually first line or heading)
            if ( $i === 0 && ( ! empty( $meta['heading_level'] ) || ! empty( $meta['bold'] ) ) ) {
                $i++;
                continue;
            }

            // Try to detect a question
            $question = $this->try_detect_question( $text, $section_lines, $i );

            if ( $question ) {
                $questions[] = $question;
                // If this question consumed following option lines, skip them
                $i += 1 + $question['_consumed_lines'];
                unset( $question['_consumed_lines'] );
            } else {
                $i++;
            }
        }

        return $questions;
    }

    /**
     * Try to detect if a line is a question and return its structured data.
     *
     * @param string $text          The line text.
     * @param array  $section_lines All lines in the section.
     * @param int    $index         Index of this line in section_lines.
     * @return array|null Question data or null if not a question.
     */
    private function try_detect_question( $text, $section_lines, $index ) {
        $len = strlen( $text );

        // Must be at least 3 chars
        if ( $len < 3 ) {
            return null;
        }

        // --- Strong question signals ---

        // Ends with question mark
        $is_question = preg_match( '/[?？]\s*$/u', $text );

        // Starts with numbered pattern: "1.", "1)", "Q1.", "Q.1", "Question 1:"
        $numbered = preg_match( '/^\s*(\d+[\.\)]|Q\.?\s*\d+[\.\)]?|(?:question|q)\s*\d+[\.\):]?)/i', $text, $num_match );

        // Starts with imperative verb
        $imperative = preg_match( '/^(?:please|provide|enter|list|describe|state|give|write|name|specify|indicate|tell|explain|share|detail|outline|summarize|brief|note|record|fill)\s/i', $text );

        // Field label pattern: "Full Name:", "Email Address:", "Company Name:"
        $field_label = preg_match( '/^[A-Z][A-Za-z\s&\-]+(?:\s+(?:Name|Address|Number|Phone|Email|Date|Company|Position|Title|City|Country|State|Province|Zip|Postal|Code|Reference|ID|Number|Amount|Percentage|Rate|Website|URL|Fax|Mobile|Gender|Age|DOB|Location|Industry|Sector|Role|Department|Division|Registration|Tax|VAT|Bank|Account|Contact|Occupation|Qualification|Education|Experience|Comments|Notes|Remarks|Signature|Consent|Agreement|Preference|Status|Type|Category|Description|Details|Information|Background|History|Period|Duration|Frequency|Budget|Revenue|Turnover|Employees|Staff|Size|Range|Scale|Volume|Capacity|Quantity|Units|Level|Grade|Score|Rating|Percentage|Proportion|Share|Ownership|Equity|Debt|Loan|Mortgage|Interest|Rate|Term|Period|Duration|Start|End|From|To|Beginning|Ending|Commencement|Termination|Expiry|Renewal))\s*:?\s*$/i', $text );

        // Check for Y/N or Yes/No indicator
        $is_yesno = preg_match( '/\b(?:yes\s*[\/\|]\s*no|y\s*[\/\|]\s*n|yes\s+or\s+no|y\s+or\s+n)\b/i', $text );

        // Collect following lines for options detection
        $following_lines = array();
        $next_idx = $index + 1;
        while ( $next_idx < count( $section_lines ) && $next_idx < $index + 15 ) {
            $next_text = trim( $section_lines[ $next_idx ]['text'] );
            if ( $next_text === '' ) {
                break;
            }
            // Stop if the next line looks like a new question or heading
            if ( preg_match( '/^\s*(\d+[\.\)]|Q\.?\s*\d+[\.\)]?|(?:question|q)\s*\d+[\.\):]?)/i', $next_text ) ) {
                break;
            }
            $following_lines[] = $next_text;
            $next_idx++;
        }

        // Detect multi-choice options (pass the line itself too, for \x01 checkbox markers)
        $options = $this->detect_options( $following_lines );
        $consumed = count( $options ) > 0 ? count( $this->detect_option_lines( $following_lines ) ) : 0;

        // Score this line as a question
        $score = 0;
        if ( $is_question )  $score += 0.5;
        if ( $numbered )     $score += 0.4;
        if ( $imperative )   $score += 0.35;
        if ( $field_label )  $score += 0.3;
        if ( ! empty( $options ) ) $score += 0.25;

        if ( $score < self::QUESTION_CONFIDENCE ) {
            return null;
        }

        // Strip leading number from the label
        $label = preg_replace( '/^\s*\d+[\.\)]\s+/', '', $text );
        $label = preg_replace( '/^\s*Q\.?\s*\d+[\.\)]?\s*/i', '', $label );
        $label = preg_replace( '/^\s*(?:question|q)\s*\d+[\.\):]?\s*/i', '', $label );
        $label = trim( $label );

        // Strip trailing colon for field labels
        $label = rtrim( $label, ': ' );
        $label = trim( $label );

        // Detect question type
        $q_type     = 'text';
        $q_options  = array();
        $q_required = true;

        if ( $is_yesno || preg_match( '/\b(?:are you|is it|do you|does it|have you|has your|will you|would you|could you|should you|can you|did you)\b/i', $text ) ) {
            $q_type = 'radio';
            $q_options = array(
                array( 'value' => 'yes', 'label' => 'Yes' ),
                array( 'value' => 'no',  'label' => 'No' ),
            );
        } elseif ( ! empty( $options ) ) {
            if ( preg_match( '/\b(?:select|choose|pick|which)\b/i', $text ) || count( $options ) > 5 ) {
                $q_type = 'select';
            } else {
                $q_type = 'radio';
            }
            foreach ( $options as $opt ) {
                $q_options[] = array(
                    'value' => sanitize_title( $opt ),
                    'label' => $opt,
                );
            }
        } elseif ( preg_match( '/\b(?:email|e-mail|email\s*address)\b/i', $text ) ) {
            $q_type = 'email';
        } elseif ( preg_match( '/\b(?:phone|telephone|mobile|cell|contact\s*number)\b/i', $text ) ) {
            $q_type = 'phone';
        } elseif ( preg_match( '/\b(?:date\s*(?:of\s*)?(?:birth|incorporation|establishment|formation)|dob|d\.?o\.?b\.?|when\s*(?:did|was|were|is|are)|start\s*date|end\s*date|deadline|due\s*date|expiry|commencement|effective)\b/i', $text ) ) {
            $q_type = 'date';
        } elseif ( preg_match( '/\b(?:how\s*many|quantity|number\s*of|count|amount|total|budget|revenue|turnover|price|cost|fee|salary|wage|income|percentage|rate|years?|months?|weeks?|employees?|staff|headcount|size)\b/i', $text ) ) {
            $q_type = 'number';
        } elseif ( preg_match( '/\b(?:describe|explain|elaborate|tell\s*us|provide\s*details?|details?|comments?|notes?|remarks?|additional\s*information|further\s*information|background|history|narrative|summary|overview|why\s+(?:is|are|did|do|does|was|were|have)|how\s+(?:do|does|did|is|are|would|could|has|have))\b/i', $text ) ) {
            $q_type = 'textarea';
        } elseif ( preg_match( '/\b(?:list|enumerate|all|each|every|multiple|various|types?\s+of|kinds?\s+of|names?\s+of)\b/i', $text ) && preg_match( '/[?？]\s*$/u', $text ) ) {
            $q_type = 'textarea';
        } elseif ( preg_match( '/\b(?:attach|upload|provide\s+document|submit|send|enclose|include\s+(?:a\s+)?(?:copy|copies|document|file|cv|resume|certificate|proof|evidence|reference))\b/i', $text ) ) {
            $q_type = 'file';
        } elseif ( preg_match( '/\b(?:company\s*name|business\s*name|organization\s*name|organisation\s*name|entity\s*name|trading\s*name|legal\s*name|registered\s*name)\b/i', $text ) ) {
            $q_type = 'text';
        } elseif ( preg_match( '/\b(?:website|web\s*site|url|link|web\s*address|domain)\b/i', $text ) ) {
            $q_type = 'text';
        } elseif ( preg_match( '/\b(?:select|choose|pick|prefer|option)\b/i', $text ) ) {
            $q_type = 'select';
        }

        // Build question data
        $question = array(
            'type'        => $q_type,
            'label'       => $label,
            'placeholder' => $this->generate_placeholder( $q_type, $label ),
            'required'    => $q_required,
            'help_text'   => '',
            'options'     => $q_options,
            '_consumed_lines' => $consumed,
        );

        return $question;
    }

    /**
     * Detect multiple-choice options from following lines.
     *
     * @param array $following_lines Lines after a potential question.
     * @return array Array of option text strings.
     */
    private function detect_options( $following_lines ) {
        $options = array();
        $max_options = 10;

        foreach ( $following_lines as $line ) {
            if ( count( $options ) >= $max_options ) {
                break;
            }

            $trimmed = trim( $line );

            // Skip very long lines (not options)
            if ( strlen( $trimmed ) > 120 ) {
                break;
            }

            // Bullet point or lettered options: a), b), c), A., B., C., a., b., c.
            if ( preg_match( '/^\s*(?:[a-z][\.\)]|[A-Z][\.\)]|•|○|◦|▪|▫|►|▸|–|—|·|‣)\s*(.{1,100})$/u', $trimmed, $m ) ) {
                $opt_text = trim( $m[1] );
                if ( strlen( $opt_text ) >= 1 && strlen( $opt_text ) <= 100 ) {
                    $options[] = $opt_text;
                }
                continue;
            }

            // Numbered sub-items: i), ii), iii) or I., II., III.
            if ( preg_match( '/^\s*(?:i{1,3}v{0,3}[\.\)]|I{1,3}V{0,3}[\.\)])\s*(.{1,100})$/u', $trimmed, $m ) ) {
                $opt_text = trim( $m[1] );
                if ( strlen( $opt_text ) >= 1 && strlen( $opt_text ) <= 100 ) {
                    $options[] = $opt_text;
                }
                continue;
            }

            // Dash-prefixed option: "- Option text"
            if ( preg_match( '/^[-–]\s+(.{1,100})$/u', $trimmed, $m ) ) {
                $opt_text = trim( $m[1] );
                if ( strlen( $opt_text ) >= 1 && strlen( $opt_text ) <= 100 ) {
                    $options[] = $opt_text;
                }
                continue;
            }

            // Simple checkbox-style: "[ ] Option" or "☐ Option"
            if ( preg_match( '/^(?:\[[ x]\]|\[?\s*[☐☑☒✓✗]\s*\]?)\s*(.{1,100})$/u', $trimmed, $m ) ) {
                $opt_text = trim( $m[1] );
                if ( strlen( $opt_text ) >= 1 && strlen( $opt_text ) <= 100 ) {
                    $options[] = $opt_text;
                }
                continue;
            }

            // PDF checkbox markers: \x01 (SOH) used as checkbox bullet
            // e.g., "\x01 English \x01 Afrikaans" or "\x01 Option text"
            if ( preg_match( '/^\x01\s*(.{1,100})$/u', $trimmed, $m ) ) {
                // May contain multiple \x01-separated options on one line
                $parts = preg_split( '/\x01\s*/', $m[1] );
                foreach ( $parts as $part ) {
                    $part = trim( $part );
                    if ( strlen( $part ) >= 1 && strlen( $part ) <= 100 ) {
                        $options[] = $part;
                    }
                }
                continue;
            }

            // \x01 mixed with text anywhere in line
            if ( strpos( $trimmed, "\x01" ) !== false && count( $options ) === 0 ) {
                $parts = preg_split( '/\x01\s*/', $trimmed );
                foreach ( $parts as $part ) {
                    $part = trim( $part );
                    if ( strlen( $part ) >= 1 && strlen( $part ) <= 100 ) {
                        $options[] = $part;
                    }
                }
                if ( count( $options ) > 0 ) {
                    continue;
                }
            }

            // If it's a short line and doesn't look like a question, might be an option
            if ( strlen( $trimmed ) <= 60 && ! preg_match( '/[?？]/u', $trimmed )
                 && ! preg_match( '/^\s*\d+[\.\)]\s+/', $trimmed ) ) {
                // Only accept if it follows a pattern (we already detected 1+ option)
                if ( count( $options ) > 0 ) {
                    $options[] = $trimmed;
                }
            }

            // Break on any empty-ish or long line
            if ( strlen( $trimmed ) > 80 ) {
                break;
            }
        }

        return $options;
    }

    /**
     * Return the number of lines that were consumed as option lines.
     */
    private function detect_option_lines( $following_lines ) {
        $count = 0;
        foreach ( $following_lines as $line ) {
            $trimmed = trim( $line );
            if ( preg_match( '/^\s*(?:[a-z][\.\)]|[A-Z][\.\)]|•|○|–|—|[-–]\s+|[☐☑])/u', $trimmed ) ) {
                $count++;
            } elseif ( preg_match( '/^\s*(?:i{1,3}v{0,3}[\.\)]|I{1,3}V{0,3}[\.\)])/u', $trimmed ) ) {
                $count++;
            } elseif ( preg_match( '/^(?:\[[ x]\]|\[?\s*[☐☑]\s*\]?)\s*/u', $trimmed ) ) {
                $count++;
            } elseif ( strpos( $trimmed, "\x01" ) !== false ) {
                $count++;
            } else {
                break;
            }
        }
        return $count;
    }

    /* =========================================================================
     * Helper Methods
     * ====================================================================== */

    /**
     * Generate a contextual placeholder based on question type and label.
     *
     * @param string $type  Question type.
     * @param string $label Question label.
     * @return string Placeholder text.
     */
    private function generate_placeholder( $type, $label ) {
        $placeholders = array(
            'text'     => 'Enter your answer',
            'textarea' => 'Please provide details...',
            'email'    => 'e.g. name@example.com',
            'phone'    => 'e.g. +27 12 345 6789',
            'date'     => 'e.g. 2024-01-15',
            'number'   => 'Enter a number',
            'file'     => 'Upload a file',
            'select'   => 'Select an option',
            'radio'    => 'Choose one',
            'checkbox' => 'Select all that apply',
        );

        if ( isset( $placeholders[ $type ] ) ) {
            return $placeholders[ $type ];
        }
        return '';
    }

    /**
     * Calculate the uppercase ratio of a string (for ALL CAPS detection).
     *
     * @param string $text Input text.
     * @return float Ratio 0-1.
     */
    private function uppercase_ratio( $text ) {
        $alpha_count = 0;
        $upper_count = 0;

        for ( $i = 0; $i < strlen( $text ); $i++ ) {
            $ch = $text[ $i ];
            if ( ctype_alpha( $ch ) ) {
                $alpha_count++;
                if ( ctype_upper( $ch ) ) {
                    $upper_count++;
                }
            }
        }

        return ( $alpha_count > 0 ) ? ( $upper_count / $alpha_count ) : 0;
    }

    /**
     * Clean a section title by removing leading numbers and extra whitespace.
     *
     * @param string $title Raw section title.
     * @return string Cleaned title.
     */
    private function clean_section_title( $title ) {
        // Remove leading "Section X:", "Part X:", etc.
        $title = preg_replace( '/^(?:section|part|chapter|module)\s+[\divx]+[\.\):]\s*/i', '', $title );
        // Remove leading number like "1." or "1)"
        $title = preg_replace( '/^\s*\d+[\.\)]\s+/', '', $title );
        // Remove trailing colon
        $title = rtrim( $title, ':' );
        // Collapse whitespace
        $title = preg_replace( '/\s+/', ' ', $title );
        return trim( $title );
    }

    /**
     * Convert a filename to a readable template name.
     *
     * @param string $filename Original filename.
     * @return string Human-readable name.
     */
    private function filename_to_name( $filename ) {
        $name = pathinfo( $filename, PATHINFO_FILENAME );
        // Replace underscores and hyphens with spaces
        $name = str_replace( array( '_', '-' ), ' ', $name );
        // Title case
        $name = ucwords( $name );
        return $name;
    }

    /**
     * Split extracted text into lines.
     *
     * @param string $text Raw extracted text.
     * @return array Array of lines.
     */
    private function split_into_lines( $text ) {
        $lines = preg_split( '/\r?\n/', $text );
        return array_map( 'trim', $lines );
    }

    /**
     * Clean extracted lines: remove empty ones, merge broken words, deduplicate.
     *
     * @param array $lines Raw lines (strings or arrays with text+meta).
     * @return array Cleaned lines.
     */
    private function clean_lines( $lines ) {
        $cleaned = array();
        $prev_text = '';

        foreach ( $lines as $line ) {
            $text = is_array( $line ) ? trim( $line['text'] ) : trim( $line );
            $meta = is_array( $line ) ? ( isset( $line['meta'] ) ? $line['meta'] : array() ) : array();

            // Skip empty lines
            if ( $text === '' ) {
                $prev_text = '';
                continue;
            }

            // Skip very short garbage lines (single characters, symbols)
            if ( strlen( $text ) === 1 && ! ctype_alpha( $text ) ) {
                continue;
            }

            // Lines starting with \x01 (SOH/PDF checkbox marker) are valid — keep them
            // but trim leading/trailing whitespace around them

            // Skip duplicate consecutive lines (handles repeated PDF headers/footers)
            if ( $text === $prev_text ) {
                continue;
            }

            // Merge line if previous was a cut-off word (doesn't end with punctuation/space)
            if ( ! empty( $cleaned ) && ! empty( $prev_text ) ) {
                $prev = $cleaned[ count( $cleaned ) - 1 ];
                $prev_text_str = is_array( $prev ) ? $prev['text'] : $prev;

                // If previous line doesn't end with sentence-ending punctuation
                // and current line is short, merge them
                if ( ! preg_match( '/[\.\?!:;,\-\s]$/', $prev_text_str )
                     && strlen( $text ) < 60
                     && ! preg_match( '/^[A-Z]/', $text ) ) {
                    // Merge into previous
                    if ( is_array( $prev ) ) {
                        $cleaned[ count( $cleaned ) - 1 ]['text'] = $prev_text_str . ' ' . $text;
                    } else {
                        $cleaned[ count( $cleaned ) - 1 ] = $prev_text_str . ' ' . $text;
                    }
                    $prev_text = $text;
                    continue;
                }
            }

            $cleaned[] = array(
                'text' => $text,
                'meta' => $meta,
            );
            $prev_text = $text;
        }

        return $cleaned;
    }
}

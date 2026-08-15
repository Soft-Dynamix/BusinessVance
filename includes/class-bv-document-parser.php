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
    const SECTION_HEADING_CONFIDENCE = 0.35;

    /**
     * Minimum confidence score for a line to be considered a question.
     */
    const QUESTION_CONFIDENCE = 0.3;

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
     * Uses /Length-based stream extraction for accurate boundaries and supports
     * multi-filter chains (e.g., ASCII85Decode + FlateDecode).
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

        // First pass: extract text from BT-containing streams only (clean, fast)
        $lines = array();
        foreach ( $streams as $stream ) {
            $text = $this->extract_pdf_text_from_stream( $stream );
            if ( ! empty( $text ) ) {
                $lines = array_merge( $lines, $this->split_into_lines( $text ) );
            }
        }

        $cleaned = $this->clean_lines( $lines );

        // Fallback: if first pass yielded zero readable lines, try ALL streams
        // (some PDFs may not use BT/ET text blocks or use unusual encoding).
        // clean_lines() binary filter will catch any garbage.
        if ( empty( $cleaned ) ) {
            foreach ( $streams as $stream ) {
                $text = $this->extract_pdf_text_from_stream( $stream, false );
                if ( ! empty( $text ) ) {
                    $lines = array_merge( $lines, $this->split_into_lines( $text ) );
                }
            }
            $cleaned = $this->clean_lines( $lines );
        }

        return $cleaned;
    }

    /**
     * Maximum raw stream size to process (bytes). Streams larger than this
     * are images or fonts, never text content. Keep memory safe.
     */
    const MAX_STREAM_SIZE = 524288; // 512 KB

    /**
     * Extract and decode all PDF content streams using /Length-based boundaries.
     *
     * This method properly handles:
     * - /Length dictionary entries for accurate stream boundaries
     * - Indirect /Length references (e.g., /Length 12 0 R)
     * - Filter arrays with multiple filters applied in order
     * - ASCII85Decode + FlateDecode double-filter chains
     * - Skips large streams (> 512KB) that are images/fonts
     *
     * @param string $content Raw PDF file content.
     * @return array Array of decompressed stream strings.
     */

    private function extract_pdf_streams( $content ) {
        $decoded_streams = array();

        // Find all indirect objects
        $obj_count = preg_match_all( '/(\d+)\s+\d+\s+obj\b/s', $content, $obj_matches, PREG_OFFSET_CAPTURE );
        if ( ! $obj_count ) {
            return $this->extract_pdf_streams_simple( $content );
        }

        for ( $i = 0; $i < $obj_count; $i++ ) {
            $obj_num  = (int) $obj_matches[1][ $i ][0];
            $obj_start = $obj_matches[0][ $i ][1];

            // Find the matching endobj
            $endobj_pos = strpos( $content, 'endobj', $obj_start );
            if ( $endobj_pos === false ) {
                continue;
            }

            $obj_body = substr( $content, $obj_start, $endobj_pos - $obj_start );

            // Check if this object contains a stream
            $stream_keyword_pos = strpos( $obj_body, 'stream' );
            if ( $stream_keyword_pos === false ) {
                continue;
            }

            // Dictionary is everything before 'stream'
            $dict_part = substr( $obj_body, 0, $stream_keyword_pos );

            // Skip image objects
            if ( preg_match( '/\/Subtype\s*\/Image/', $dict_part ) ) {
                continue;
            }

            // Skip font objects
            if ( preg_match( '/\/Subtype\s*\/(?:Type1|Type2|Type3|CIDFontType0|CIDFontType2|TrueType)/', $dict_part )
                 || preg_match( '/\/Type\s*\/Font/', $dict_part ) ) {
                continue;
            }

            // Skip CMap / ToUnicode streams (character maps, not page content)
            if ( preg_match( '/\/CIDInit|begincmap|\/CMapName|\/ToUnicode|\/Encoding/', $dict_part ) ) {
                continue;
            }

            // Skip XRef streams (binary index tables, not content)
            if ( preg_match( '/\/Type\s*\/XRef/', $dict_part ) ) {
                continue;
            }

            // Detect filter(s)
            $filters = array();

            // Array of filters: /Filter [ /ASCII85Decode /FlateDecode ]
            if ( preg_match( '/\/Filter\s*\[\s*(.*?)\s*\]/s', $dict_part, $filter_array_match ) ) {
                $filter_str = $filter_array_match[1];
                preg_match_all( '/\/(\w+)/', $filter_str, $filter_names );
                $filters = $filter_names[1];
            }
            // Single filter: /Filter /FlateDecode
            elseif ( preg_match( '/\/Filter\s*\/(\w+)/', $dict_part, $filter_single_match ) ) {
                $filters = array( $filter_single_match[1] );
            }

            // Skip image-related filters
            if ( in_array( 'DCTDecode', $filters, true ) || in_array( 'JPXDecode', $filters, true ) ) {
                continue;
            }

            // Get /Length for accurate stream boundary
            $stream_length = false;

            // Direct: /Length N
            if ( preg_match( '/\/Length\s+(\d+)/', $dict_part, $len_match ) ) {
                $stream_length = (int) $len_match[1];
            }

            // Indirect: /Length N 0 R — resolve the reference
            if ( $stream_length === false && preg_match( '/\/Length\s+(\d+)\s+(\d+)\s+R/', $dict_part, $len_indirect ) ) {
                $ref_obj = (int) $len_indirect[1];
                $ref_gen = (int) $len_indirect[2];
                // Find the referenced object and extract its numeric value
                $stream_length = $this->resolve_indirect_length( $content, $ref_obj, $ref_gen );
            }

            // Calculate absolute stream data start position in $content
            $stream_data_offset = $obj_start + $stream_keyword_pos + strlen( 'stream' );

            // Skip the newline(s) after 'stream'
            if ( substr( $content, $stream_data_offset, 2 ) === "\r\n" ) {
                $stream_data_offset += 2;
            } elseif ( substr( $content, $stream_data_offset, 1 ) === "\n" ) {
                $stream_data_offset += 1;
            } elseif ( substr( $content, $stream_data_offset, 1 ) === "\r" ) {
                $stream_data_offset += 1;
            }

            // Extract stream data using /Length
            if ( $stream_length !== false && $stream_length > 0 ) {
                $raw_data = substr( $content, $stream_data_offset, $stream_length );
            } else {
                // Fallback: use regex to find endstream
                $remaining = substr( $content, $stream_data_offset );
                if ( preg_match( '/(.*?)\r?\nendstream/s', $remaining, $endstream_match ) ) {
                    $raw_data = $endstream_match[1];
                } elseif ( preg_match( '/(.*?)endstream/s', $remaining, $endstream_match ) ) {
                    $raw_data = $endstream_match[1];
                } else {
                    continue;
                }
            }

            if ( empty( $raw_data ) ) {
                continue;
            }

            // Skip large streams — they are images/fonts, never text content.
            // Text content streams in PDFs are typically < 50 KB even for complex pages.
            if ( strlen( $raw_data ) > self::MAX_STREAM_SIZE ) {
                continue;
            }

            // Apply filter chain in order
            $data = $raw_data;

            if ( empty( $filters ) ) {
                // No filter — raw data
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
     * Resolve an indirect object reference to get its numeric value.
     *
     * For /Length references, the referenced object typically contains just a number.
     *
     * @param string $content Raw PDF content.
     * @param int    $obj_num Object number.
     * @param int    $gen_num Generation number.
     * @return int|false The numeric value, or false if not found.
     */
    private function resolve_indirect_length( $content, $obj_num, $gen_num ) {
        $pattern = '/' . preg_quote( $obj_num, '/' ) . '\s+' . preg_quote( $gen_num, '/' ) . '\s+obj\s*(\d+)\s+endobj/s';
        if ( preg_match( $pattern, $content, $m ) ) {
            return (int) $m[1];
        }
        return false;
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
                if ( strlen( $data ) > 6 ) {
                    $result = @gzinflate( substr( $data, 2, -4 ) );
                    if ( $result !== false ) {
                        return $result;
                    }
                }
                return false;

            case 'ASCII85Decode':
                return $this->decode_ascii85( $data );

            case 'ASCIIHexDecode':
                return $this->decode_ascii_hex( $data );

            case 'RunLengthDecode':
                return $this->decode_run_length( $data );

            case 'LZWDecode':
                return $this->decode_lzw( $data );

            default:
                // Unknown/unsupported filter — skip this stream entirely
                return false;
        }
    }

    /**
     * Decode Run Length Encoding (RLE) used in some PDFs.
     *
     * @param string $data RLE-encoded data.
     * @return string|false Decoded data, or false on failure.
     */
    private function decode_run_length( $data ) {
        $result  = '';
        $len     = strlen( $data );
        $i       = 0;

        while ( $i < $len ) {
            if ( $i >= $len ) {
                break;
            }
            $header = ord( $data[ $i ] );
            $i++;

            if ( $header < 128 ) {
                // Literal run of (header + 1) bytes
                $count = $header + 1;
                if ( $i + $count > $len ) {
                    return false;
                }
                $result .= substr( $data, $i, $count );
                $i += $count;
            } elseif ( $header > 128 ) {
                // Repeated byte: (257 - header) copies
                $count = 257 - $header;
                if ( $i >= $len ) {
                    return false;
                }
                $result .= str_repeat( $data[ $i ], $count );
                $i++;
            }
            // 128 = end-of-data marker
        }

        return $result;
    }

    /**
     * Decode LZW (Lempel-Ziv-Welch) encoded data as used in PDFs.
     *
     * Implements the PDF variant of LZW with clear code (256) and EOD code (257).
     *
     * @param string $data LZW-encoded data.
     * @return string|false Decoded data, or false on failure.
     */
    private function decode_lzw( $data ) {
        $CLEAR = 256;
        $EOD   = 257;

        $table     = array();
        $tableSize = 258;
        for ( $i = 0; $i < 256; $i++ ) {
            $table[ $i ] = chr( $i );
        }

        $codeSize = 9;
        $bitBuf   = 0;
        $bitCount = 0;
        $byteIdx  = 0;
        $dataLen  = strlen( $data );
        $output   = '';
        $oldCode  = null;

        while ( $byteIdx < $dataLen ) {
            while ( $bitCount < $codeSize && $byteIdx < $dataLen ) {
                $bitBuf   += ord( $data[ $byteIdx++ ] ) << $bitCount;
                $bitCount += 8;
            }

            if ( $bitCount < $codeSize ) {
                break;
            }

            $code     = $bitBuf & ( ( 1 << $codeSize ) - 1 );
            $bitBuf  >>= $codeSize;
            $bitCount -= $codeSize;

            if ( $code === $CLEAR ) {
                $tableSize = 258;
                $codeSize  = 9;
                $table     = array();
                for ( $i = 0; $i < 256; $i++ ) {
                    $table[ $i ] = chr( $i );
                }
                $oldCode = null;
                continue;
            }

            if ( $code === $EOD ) {
                break;
            }

            if ( $oldCode === null ) {
                if ( ! isset( $table[ $code ] ) ) {
                    return false;
                }
                $output  .= $table[ $code ];
                $oldCode  = $code;
                continue;
            }

            if ( isset( $table[ $code ] ) ) {
                $entry = $table[ $code ];
            } elseif ( $code === $tableSize ) {
                $entry = $table[ $oldCode ] . $table[ $oldCode ][0];
            } else {
                return false;
            }

            $output .= $entry;

            if ( $tableSize < 4096 ) {
                $table[ $tableSize ] = $table[ $oldCode ] . $entry[0];
                $tableSize++;
                if ( $tableSize > ( 1 << $codeSize ) && $codeSize < 12 ) {
                    $codeSize++;
                }
            }

            $oldCode = $code;
        }

        return $output;
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
     * Extract text from a PDF content stream using position-based extraction.
     *
     * Walks through the stream finding all text-showing operators (Tj, TJ, ', ")
     * and line-break operators (T*, ET) by their byte offsets, sorts them by
     * position, and reconstructs the text with newlines at the correct boundaries.
     *
     * @param string $stream      Decompressed PDF stream content.
     * @param bool   $require_bt Whether to require BT operator (default true).
     * @return string Extracted text with \n at line boundaries.
     */
    private function extract_pdf_text_from_stream( $stream, $require_bt = true ) {
        // Only process streams that contain text operators (BT = Begin Text).
        // Non-content streams (fonts, CMaps, metadata) will be skipped entirely.
        if ( $require_bt && ! preg_match( '/\\bBT\\b/', $stream ) ) {
            return '';
        }

        $tokens = array();

        // --- Collect text-showing operators with byte offsets ---

        // TJ arrays: [(text1) -N (text2)] TJ  (most common in modern PDFs)
        if ( preg_match_all( '/\[((?:\([^)]*\)|[^\]])*?)\]\s*TJ/s', $stream, $m, PREG_OFFSET_CAPTURE ) ) {
            foreach ( $m[1] as $match ) {
                $block   = $match[0];
                $pos     = $match[1];
                $decoded = '';
                preg_match_all( '/\(([^)]*)\)/', $block, $segs );
                foreach ( $segs[1] as $seg ) {
                    $decoded .= $this->decode_pdf_string( $seg );
                }
                $decoded = trim( $decoded );
                if ( '' !== $decoded ) {
                    $tokens[] = array( 'pos' => $pos, 'type' => 'text', 'text' => $decoded );
                }
            }
        }

        // Tj operator: (text) Tj
        if ( preg_match_all( '/\(([^)]*)\)\s*Tj/', $stream, $m, PREG_OFFSET_CAPTURE ) ) {
            foreach ( $m[1] as $match ) {
                $decoded = trim( $this->decode_pdf_string( $match[0] ) );
                if ( '' !== $decoded ) {
                    $tokens[] = array( 'pos' => $match[1], 'type' => 'text', 'text' => $decoded );
                }
            }
        }

        // ' (single-quote) operator: (text) '  — move to next line + show
        if ( preg_match_all( '/\(([^)]*)\)\s*\'/', $stream, $m, PREG_OFFSET_CAPTURE ) ) {
            foreach ( $m[1] as $match ) {
                $decoded = trim( $this->decode_pdf_string( $match[0] ) );
                if ( '' !== $decoded ) {
                    $tokens[] = array( 'pos' => $match[1], 'type' => 'text_nl', 'text' => $decoded );
                }
            }
        }

        // " (double-quote) operator: (text) "  — set spacing + show
        if ( preg_match_all( '/\(([^)]*)\)\s*"/', $stream, $m, PREG_OFFSET_CAPTURE ) ) {
            foreach ( $m[1] as $match ) {
                $decoded = trim( $this->decode_pdf_string( $match[0] ) );
                if ( '' !== $decoded ) {
                    $tokens[] = array( 'pos' => $match[1], 'type' => 'text', 'text' => $decoded );
                }
            }
        }

        // --- Collect line-break operators with byte offsets ---

        // T* — move to start of next line
        if ( preg_match_all( '/\bT\*(?=[\s\)]|$)/', $stream, $m, PREG_OFFSET_CAPTURE ) ) {
            foreach ( $m[0] as $match ) {
                $tokens[] = array( 'pos' => $match[1], 'type' => 'newline' );
            }
        }

        // ET — end text object (always followed by a new visual block)
        if ( preg_match_all( '/\bET\b/', $stream, $m, PREG_OFFSET_CAPTURE ) ) {
            foreach ( $m[0] as $match ) {
                $tokens[] = array( 'pos' => $match[1], 'type' => 'newline' );
            }
        }

        // --- Sort all tokens by byte offset ---
        usort( $tokens, function ( $a, $b ) {
            return $a['pos'] - $b['pos'];
        } );

        // --- Build output text ---
        $text  = '';
        $lines = array();          // collect per-visual-line text
        $buf   = '';               // current line buffer

        foreach ( $tokens as $tok ) {
            if ( 'newline' === $tok['type'] ) {
                if ( '' !== trim( $buf ) ) {
                    $lines[] = trim( $buf );
                }
                $buf = '';
            } elseif ( 'text_nl' === $tok['type'] ) {
                // single-quote already implies a new line before the text
                if ( '' !== trim( $buf ) ) {
                    $lines[] = trim( $buf );
                }
                $buf = $tok['text'] . ' ';
            } else {
                $buf .= $tok['text'] . ' ';
            }
        }
        // Flush remaining buffer
        if ( '' !== trim( $buf ) ) {
            $lines[] = trim( $buf );
        }

        return implode( "\n", $lines );
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
        $this->extract_docx_paragraphs( $doc, $w_ns, $namespaces, $lines );

        return $this->clean_lines( $lines );
    }

    /**
     * Recursively extract paragraphs from DOCX XML.
     *
     * @param \SimpleXMLElement $node  XML node.
     * @param string           $w_ns  Word namespace prefix.
     * @param array            $namespaces  Registered XML namespaces.
     * @param array            &$lines Accumulated lines.
     * @param int              $depth Current depth for nested elements.
     */
    private function extract_docx_paragraphs( $node, $w_ns, $namespaces, &$lines, $depth = 0 ) {
        $prefix = $w_ns ? $w_ns . ':' : '';

        // Look for paragraph elements
        foreach ( $node->children() as $child ) {
            $local_name = $child->getName();

            if ( $local_name === 'body' || $local_name === 'sectPr' || $local_name === 'document' ) {
                $this->extract_docx_paragraphs( $child, $w_ns, $namespaces, $lines, $depth );
                continue;
            }

            if ( $local_name === 'p' ) {
                $para_text = '';
                $is_bold = false;
                $font_size = 0;
                $style_name = '';

                // Check paragraph style
                $ns_uri    = ( $w_ns && isset( $namespaces[ $w_ns ] ) ) ? $namespaces[ $w_ns ] : null;
                $p_children = $child->children( $ns_uri );
                $p_pr = null;
                foreach ( $p_children as $p_child ) {
                    if ( $p_child->getName() === 'pPr' ) {
                        $p_pr = $p_child;
                        break;
                    }
                }
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
                $this->extract_docx_paragraphs( $child, $w_ns, $namespaces, $lines, $depth + 1 );
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
        for ( $i = 0; $i < min( 10, count( $normalized ) ); $i++ ) {
            $line = $normalized[ $i ];
            $text = trim( $line['text'] );
            $meta = $line['meta'];

            // Skip very short lines (likely artifacts)
            if ( strlen( $text ) < 3 ) {
                continue;
            }

            // Skip common noise lines (headers, footers, branding)
            if ( preg_match( '/\|.*Page\s+\d+/i', $text ) ) {
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

            // ALL CAPS lines of reasonable length are likely titles (for PDFs)
            if ( strlen( $text ) >= 5 && strlen( $text ) <= 80 && $this->uppercase_ratio( $text ) > 0.8 ) {
                return $text;
            }

            // Numbered heading like "1. Client Information" — use as title if first heading
            if ( preg_match( '/^\d+[\.\)]\s+[A-Z]/', $text ) && strlen( $text ) >= 5 && strlen( $text ) <= 80 ) {
                return $this->clean_section_title( $text );
            }
        }

        // Fallback: use first meaningful line
        if ( ! empty( $normalized ) ) {
            for ( $i = 0; $i < min( 5, count( $normalized ) ); $i++ ) {
                $first = trim( $normalized[ $i ]['text'] );
                if ( strlen( $first ) >= 5 && strlen( $first ) <= 120 && ! preg_match( '/\|.*Page\s+\d+/i', $first ) ) {
                    return $first;
                }
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

        // Skip ALL headings before the first numbered heading (N. or N) pattern.
        // These are document titles, not section headings.
        $first_numbered = -1;
        foreach ( $filtered as $idx => $f ) {
            if ( preg_match( '/^\s*\d+[\.\)]\s+/', $normalized[ $f['index'] ]['text'] ) ) {
                $first_numbered = $idx;
                break;
            }
        }
        if ( $first_numbered > 0 ) {
            $filtered = array_slice( $filtered, $first_numbered );
        } elseif ( $first_numbered === -1 && ! empty( $filtered ) && $filtered[0]['index'] < 3 ) {
            // Fallback: if no numbered heading found, skip first heading if it's in the first 3 lines
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
                     && ! preg_match( '/^\d+(\.\d+)?[\).]/', $next_text )
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
     * Optimized for both DOCX (with metadata) and PDF (text-only) documents.
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

        // Check for table header lines (ALL CAPS column headers) — skip these
        if ( preg_match( '/^[A-Z][A-Z\s\/&]+$/u', $text ) && $len > 5 ) {
            // Likely a table header row — not a section heading
            // But allow if it's a single word or very short (like "INTRODUCTION")
            if ( substr_count( $text, '  ' ) > 0 || preg_match( '/[A-Z]\s{2,}[A-Z]/', $text ) ) {
                return 0;
            }
        }

        // Must NOT be a numbered sub-question like "2.1 Briefly describe..."
        // Allow numbered section headings like "1. Client Information" or "2. Business Overview"
        if ( preg_match( '/^\s*\d+\.\d+[\.\s]/', $text ) ) {
            // Sub-numbered pattern (2.1, 3.2) — always a question, never a heading
            return 0;
        }

        if ( preg_match( '/^\s*(\d+[\.\)]\s+|Q\d+[\.\)]?\s+)/', $text ) ) {
            // Numbered pattern — allow only if it looks like a section heading
            // Section heading: "1. Client Information" (short, Title Case, no question word)
            $is_question_start = preg_match( '/^\s*\d+[\.\)]\s+(?:please|provide|enter|list|describe|state|give|write|briefly|what|which|who|how|why|when|where|are|is|do|does|have|has|can|could|should|would|will|name|specify|indicate|tell|explain)\b/i', $text );
            if ( $is_question_start ) {
                return 0;
            }
            // If it's long and contains sentence-like text, it's probably a question
            if ( strlen( $text ) > 60 && preg_match( '/\b(?:your|the|this|that|a|an|of|in|for|to|with|from|by|at|on|about|into|through)\b/i', $text ) ) {
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

        // ALL CAPS text (70%+ uppercase) — strong signal for PDF headings
        $upper_ratio = $this->uppercase_ratio( $text );
        if ( $upper_ratio > 0.7 && $len >= 5 ) {
            $score += 0.4;
        } elseif ( $upper_ratio > 0.5 && $len >= 5 ) {
            $score += 0.2;
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

        // Numbered heading pattern like "1. Company Info" (short, no question mark, Title Case)
        if ( preg_match( '/^\s*\d+[\.\)]\s+[A-Z]/', $text ) && $len <= 80 && $len >= 5 ) {
            $score += 0.3;
        }

        // Roman numeral heading like "I. Executive Summary"
        if ( preg_match( '/^[IVX]+\.\s+[A-Z]/', $text ) && $len <= 80 ) {
            $score += 0.3;
        }

        // Title Case check: if most words start with uppercase, likely a heading
        $title_case_ratio = $this->title_case_ratio( $text );
        if ( $title_case_ratio > 0.7 && $len >= 8 && $len <= 80 ) {
            $score += 0.15;
        }

        // Context: if previous line was empty (gap before heading)
        if ( $index > 0 && trim( $lines[ $index - 1 ]['text'] ) === '' ) {
            $score += 0.1;
        }

        // Context: if next line is empty (gap after heading)
        if ( $index + 1 < count( $lines ) && trim( $lines[ $index + 1 ]['text'] ) === '' ) {
            $score += 0.1;
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

            // Skip the section title itself (first line is usually the title)
            // For PDFs without metadata, check if line matches section title pattern
            if ( $i === 0 ) {
                if ( ! empty( $meta['heading_level'] ) || ! empty( $meta['bold'] ) ) {
                    $i++;
                    continue;
                }
                // For PDFs: skip first non-empty line if it looks like a heading
                // (section title is already stored in the section data)
                $i++;
                continue;
            }

            // Skip lines that are clearly section headings (high confidence)
            if ( $this->calculate_section_heading_score( $text, $meta, $section_lines, $i ) >= 0.5 ) {
                $i++;
                continue;
            }

            // Skip known noise lines
            if ( $this->is_noise_line( $text ) ) {
                $i++;
                continue;
            }

            // ── Multi-line field joining ──
            // PDF forms sometimes split a single field label across two lines:
            //   "City / Province / Preferred"  +  "Country language"
            // Join them into one line so they get parsed as a single question.
            $merged_next = false;
            if ( preg_match( '/\s*\/\s*$/', $text ) && $i + 1 < $total ) {
                $next_text = trim( $section_lines[ $i + 1 ]['text'] );
                if ( $next_text !== '' && strlen( $next_text ) <= 40 && ! preg_match( '/^\d/', $next_text ) && ! preg_match( '/^[☐☑\x01]/u', $next_text ) ) {
                    // Merge and let the merged line be processed
                    $text = trim( $text ) . ' ' . $next_text;
                    $line['text'] = $text;
                    $merged_next = true;
                }
            }

            // Try to detect a question
            $question = $this->try_detect_question( $text, $section_lines, $i );

            if ( $question ) {
                $questions[] = $question;
                $consumed = $question['_consumed_lines'] ?? 0;
                $extra = $merged_next ? 1 : 0; // Consume the joined line too
                $i += 1 + $consumed + $extra;

                // If this was a split multi-field line, inject the second field
                // so it gets detected as a separate question on the next iteration.
                if ( ! empty( $question['_split_remainder'] ) ) {
                    $remainder = trim( $question['_split_remainder'] );
                    if ( $remainder ) {
                        // Insert as a virtual line at current position
                        array_splice( $section_lines, $i, 0, array(
                            array( 'text' => $remainder, 'meta' => array( 'bold' => false ) )
                        ));
                        $total = count( $section_lines ); // Update total
                    }
                }

                unset( $question['_consumed_lines'] );
                if ( isset( $question['_split_remainder'] ) ) {
                    unset( $question['_split_remainder'] );
                }
            } else {
                $i++;
            }
        }

        return $questions;
    }

    /**
     * Check if a line is noise (headers, footers, table headers, etc.).
     *
     * @param string $text Line text.
     * @return bool True if noise.
     */
    private function is_noise_line( $text ) {
        // Page footer patterns
        if ( preg_match( '/\|.*Page\s+\d+/i', $text ) ) {
            return true;
        }
        if ( preg_match( '/^\s*Page\s+\d+\s*$/i', $text ) ) {
            return true;
        }

        // Repeated branding lines (various formats)
        if ( preg_match( '/^BusinessVance\s*\|.*\|.*Page\s+\d+/i', $text ) ) {
            return true;
        }
        if ( preg_match( '/^BusinessVance\s.*Page\s+\d+/i', $text ) ) {
            return true;
        }
        if ( preg_match( '/BUSINESSVANCE.*COMPETITOR\s+ANALYSIS\s+QUESTIONNAIRE/i', $text ) ) {
            return true;
        }
        if ( $text === 'BUSINESSVANCE' || $text === 'COMPETITOR ANALYSIS QUESTIONNAIRE' ) {
            return true;
        }

        // Standalone ALL CAPS lines that are table column headers or fragments
        // e.g., "PRODUCT OR SERVICE", "DESCRIPTION", "YOUR PRICE", "PACKAGE", "DISCOUNT /"
        if ( preg_match( '/^[A-Z][A-Z\s\/&\-\.\#]+$/', $text ) && strlen( $text ) > 3 && strlen( $text ) <= 40 ) {
            return true;
        }
        // Table header lines with mixed case but containing known table column keywords
        if ( preg_match( '/^\s*(?:PRODUCT|SERVICE|DESCRIPTION|PRICE|COMPETITOR|LOCATION|WEBSITE|DISCOUNT|PACKAGE|COMPARISON|IMPORTANCE)\s/u', $text )
             && preg_match( '/\s{3,}/', $text ) ) {
            return true;
        }

        return false;
    }

    /**
     * Try to detect if a line is a question and return its structured data.
     *
     * Uses multiple heuristic signals:
     * - Question mark at end
     * - Numbered pattern (1., 1.1, Q1, etc.)
     * - Imperative verbs (describe, provide, etc.)
     * - Field label patterns (Name:, Email:, etc.)
     * - Lines ending with colon (followed by fill-in space)
     * - Checkbox groups following the line
     *
     * @param string $text          The line text.
     * @param array  $section_lines All lines in the section.
     * @param int    $index         Index of this line in section_lines.
     * @return array|null Question data or null if not a question.
     */
    /**
     * Try to split a multi-field line into two separate questions.
     *
     * PDF forms often put two labels side-by-side: "Full name Date",
     * "Business name Client reference", "Email address Contact number".
     *
     * @since 2.7.9
     * @param string $text The line text.
     * @return array|null Question data for the FIRST field, or null if not a multi-field line.
     */
    private function try_split_multi_field_line( $text ) {
        $text = trim( $text );
        if ( strlen( $text ) < 8 ) {
            return null;
        }

        // Known field-label suffixes that commonly appear in PDF forms
        $field_suffixes = array(
            'name', 'Name', 'date', 'Date', 'address', 'Address',
            'number', 'Number', 'phone', 'Phone', 'email', 'Email',
            'reference', 'Reference', 'city', 'City', 'country', 'Country',
            'province', 'Province', 'language', 'Language',
            'description', 'Description', 'details', 'Details',
            'comments', 'Comments', 'notes', 'Notes',
            'position', 'Position', 'title', 'Title',
            'company', 'Company', 'business', 'Business',
            'amount', 'Amount', 'percentage', 'Percentage',
            'status', 'Status', 'type', 'Type',
        );

        // Pattern: Two capitalized words at split points (e.g. "Full name Date")
        // Match: capitalized word(s) followed by another capitalized word that matches a field suffix
        $pattern = '/^([A-Z][A-Za-z\s\-&\/]+?\s+(?:' . implode( '|', $field_suffixes ) . '))\s{2,}([A-Z][A-Za-z\s\-&\/]+?\s*(?:' . implode( '|', $field_suffixes ) . ')?)\s*$/u';

        if ( preg_match( $pattern, $text, $m ) ) {
            $first  = trim( $m[1] );
            $second = trim( $m[2] );
            // Only split if both parts are reasonable lengths (3-40 chars)
            if ( strlen( $first ) >= 3 && strlen( $first ) <= 40 && strlen( $second ) >= 3 && strlen( $second ) <= 40 ) {
                // Return only the FIRST field; the second will be detected on the next pass
                // (we can't return two questions at once)
                return array(
                    'type'        => $this->infer_field_type( $first ),
                    'label'       => $first,
                    'placeholder' => $this->generate_placeholder( 'text', $first ),
                    'required'    => true,
                    'help_text'   => '',
                    'options'     => array(),
                    '_consumed_lines' => 0,
                    '_split_remainder' => $second,
                );
            }
        }

        return null;
    }

    /**
     * Infer question type from a field label string.
     *
     * @since 2.7.9
     * @param string $label The field label.
     * @return string Question type.
     */
    private function infer_field_type( $label ) {
        if ( preg_match( '/\b(?:email|e-mail|email\s*address)\b/i', $label ) ) {
            return 'email';
        }
        if ( preg_match( '/\b(?:phone|telephone|mobile|cell|contact\s*number)\b/i', $label ) ) {
            return 'phone';
        }
        if ( preg_match( '/\b(?:date|dob|when)\b/i', $label ) ) {
            return 'date';
        }
        if ( preg_match( '/\b(?:website|url|link|web)\b/i', $label ) ) {
            return 'text';
        }
        return 'text';
    }

    private function try_detect_question( $text, $section_lines, $index ) {
        $len = strlen( $text );

        // Must be at least 3 chars
        if ( $len < 3 ) {
            return null;
        }

        // ── Standalone checkbox/radio group detection ──
        // Lines like "☐ English ☐ Afrikaans" or "☐ Idea stage ☐ Research stage"
        // IMPORTANT: Merge CONSECUTIVE checkbox lines into one question.
        // PDF forms often have multiple checkbox lines in a row that form one question:
        //   ☐ Idea stage              ☐ Research stage
        //   ☐ Planning stage          ☐ Pre-launch
        //   ☐ Recently launched       ☐ Already operating
        $checkbox_count = substr_count( $text, '☐' ) + substr_count( $text, '☑' ) + substr_count( $text, "\x01" );
        if ( $checkbox_count >= 2 && ( preg_match( '/^[☐\x01\s]+/u', $text ) || preg_match( '/^\s*[☐\x01]/u', $text ) ) ) {
            // Collect options from this line
            $raw = trim( $text );
            $parts = preg_split( '/[☐☑\x01]\s*/u', $raw );
            $opts = array();
            foreach ( $parts as $part ) {
                $part = trim( $part );
                if ( strlen( $part ) >= 1 && strlen( $part ) <= 100 ) {
                    $opts[] = $part;
                }
            }

            // Look ahead for consecutive checkbox lines and merge their options
            $extra_consumed = 0;
            $lookahead = $index + 1;
            while ( $lookahead < count( $section_lines ) && $extra_consumed < 15 ) {
                $next_text = trim( $section_lines[ $lookahead ]['text'] );
                if ( $next_text === '' ) {
                    $lookahead++;
                    continue;
                }
                $next_cb_count = substr_count( $next_text, '☐' ) + substr_count( $next_text, '☑' ) + substr_count( $next_text, "\x01" );
                if ( $next_cb_count >= 2 && ( preg_match( '/^[☐\x01\s]+/u', $next_text ) || preg_match( '/^\s*[☐\x01]/u', $next_text ) ) ) {
                    // Merge options from this line too
                    $next_parts = preg_split( '/[☐☑\x01]\s*/u', $next_text );
                    foreach ( $next_parts as $np ) {
                        $np = trim( $np );
                        if ( strlen( $np ) >= 1 && strlen( $np ) <= 100 ) {
                            $opts[] = $np;
                        }
                    }
                    $extra_consumed += ( $lookahead - $index );
                    $lookahead++;
                } else {
                    break;
                }
            }
            // Calculate total consumed lines (from section start)
            $consumed_lines = ( $lookahead - 1 ) - $index;

            if ( count( $opts ) >= 2 ) {
                // Determine if it should be checkbox (multi-select) or radio (single-select)
                $is_language = preg_match( '/\b(?:English|Afrikaans|language|taal|prefer)\b/i', implode( ' ', $opts ) );
                $q_type = $is_language ? 'radio' : 'checkbox';
                return array(
                    'type'        => $q_type,
                    'label'       => $is_language
                                     ? 'Preferred language' : 'Select all that apply',
                    'placeholder' => '',
                    'required'    => false,
                    'help_text'   => '',
                    'options'     => array_map( function( $o ) {
                        return array( 'value' => sanitize_title( $o ), 'label' => $o );
                    }, $opts ),
                    '_consumed_lines' => $consumed_lines,
                );
            }
        }

        // ── Multi-field line splitting ──
        // PDF forms often put two labels side-by-side: "Full name Date"
        // Split into separate questions when both parts look like field labels.
        $multi_fields = $this->try_split_multi_field_line( $text );
        if ( $multi_fields !== null ) {
            return $multi_fields;
        }

        // --- Strong question signals ---

        // Ends with question mark
        $is_question = preg_match( '/[?？]\s*$/u', $text );

        // Numbered pattern: "1.", "1.1", "1)", "Q1.", "Q.1", "Question 1:"
        $numbered = preg_match( '/^\s*(\d+\.\d+[\.\s]|\d+[\.\)]|Q\.?\s*\d+[\.\)]?|(?:question|q)\s*\d+[\.\):]?)/i', $text, $num_match );

        // Extract the number for reference
        $question_number = '';
        if ( $numbered && isset( $num_match[0] ) ) {
            $question_number = trim( $num_match[0] );
        }

        // Starts with imperative verb (possibly after a number)
        $text_after_number = preg_replace( '/^\s*\d+(\.\d+)?[\.\)]\s*/', '', $text );
        $imperative = preg_match( '/^(?:please|provide|enter|list|describe|state|give|write|name|specify|indicate|tell|explain|share|detail|outline|summarize|brief|note|record|fill|briefly)\s/i', $text_after_number );

        // Field label pattern: "Full Name:", "Email Address:", "Company Name:"
        $field_label = preg_match( '/^[A-Z][A-Za-z\s&\-]+(?:\s+(?:Name|Address|Number|Phone|Email|Date|Company|Position|Title|City|Country|State|Province|Zip|Postal|Code|Reference|ID|Amount|Percentage|Rate|Website|URL|Fax|Mobile|Gender|Age|DOB|Location|Industry|Sector|Role|Department|Division|Registration|Tax|VAT|Bank|Account|Contact|Occupation|Qualification|Education|Experience|Comments|Notes|Remarks|Signature|Consent|Agreement|Preference|Status|Type|Category|Description|Details|Information|Background|History|Period|Duration|Frequency|Budget|Revenue|Turnover|Employees|Staff|Size|Range|Scale|Volume|Capacity|Quantity|Units|Level|Grade|Score|Rating|Share|Ownership|Equity|Debt|Loan|Mortgage|Interest|Term|Start|End|From|To|Beginning|Ending|Commencement|Termination|Expiry|Renewal))\s*:?\s*$/i', $text );

        // Check for Y/N or Yes/No indicator
        $is_yesno = preg_match( '/\b(?:yes\s*[\/\|]\s*no|y\s*[\/\|]\s*n|yes\s+or\s+no|y\s+or\s+n)\b/i', $text );

        // Line ends with colon — common in questionnaires for fill-in fields
        $ends_with_colon = preg_match( '/:\s*$/', $text );

        // Collect following lines for options detection
        $following_lines = array();
        $next_idx = $index + 1;
        while ( $next_idx < count( $section_lines ) && $next_idx < $index + 20 ) {
            $next_text = trim( $section_lines[ $next_idx ]['text'] );
            if ( $next_text === '' ) {
                break;
            }
            // Stop if the next line looks like a new question or heading
            if ( preg_match( '/^\s*(\d+\.\d+[\.\s]|\d+[\.\)]|Q\.?\s*\d+[\.\)]?|(?:question|q)\s*\d+[\.\):]?)/i', $next_text ) ) {
                break;
            }
            // Stop if the next line is a high-confidence section heading
            if ( $this->calculate_section_heading_score( $next_text, array(), $section_lines, $next_idx ) >= 0.5 ) {
                break;
            }
            $following_lines[] = $next_text;
            $next_idx++;
        }

        // Detect multi-choice options (pass the line itself too, for \x01 checkbox markers)
        $options = $this->detect_options( $following_lines );
        $consumed = count( $options ) > 0 ? $this->detect_option_lines( $following_lines ) : 0;

        // Score this line as a question
        $score = 0;
        if ( $is_question )                    $score += 0.5;
        if ( $numbered )                       $score += 0.35;
        if ( $imperative )                      $score += 0.35;
        if ( $field_label )                     $score += 0.4;
        if ( $is_yesno )                        $score += 0.3;
        if ( ! empty( $options ) )              $score += 0.25;
        if ( $ends_with_colon && $len >= 10 )   $score += 0.25;
        if ( $ends_with_colon && $len < 10 )    $score += 0.15;

        // Bonus: numbered sub-question pattern (2.1, 3.2) is very strong
        if ( $numbered && preg_match( '/^\s*\d+\.\d+/', $text ) ) {
            $score += 0.2;
        }

        if ( $score < self::QUESTION_CONFIDENCE ) {
            return null;
        }

        // Strip leading number from the label
        $label = preg_replace( '/^\s*\d+\.\d+\s+/', '', $text );
        $label = preg_replace( '/^\s*\d+[\.\)]\s+/', '', $label );
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

        // Check for open-ended question patterns (what/which/how/why ending with ?)
        // These should be textarea, NOT Yes/No radio
        $is_open_ended = preg_match( '/[?？]\s*$/u', $text )
            && preg_match( '/\b(?:what|which|how|why|where|when|who)\b/i', $text );

        if ( $is_yesno && ! $is_open_ended && ! empty( $options ) === false ) {
            $q_type = 'radio';
            $q_options = array(
                array( 'value' => 'yes', 'label' => 'Yes' ),
                array( 'value' => 'no',  'label' => 'No' ),
            );
        } elseif ( $is_open_ended && empty( $options ) ) {
            // Open-ended question with no options → textarea
            $q_type = 'textarea';
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
     * Handles various option formats:
     * - Lettered: a), b), c), A., B., C.
     * - Bulleted: •, ○, ▪, ►, –
     * - Dashed: - Option text
     * - Checkbox: ☐ Option, [ ] Option
     * - PDF checkbox markers: \x01 (SOH control character)
     *
     * @param array $following_lines Lines after a potential question.
     * @return array Array of option text strings.
     */
    private function detect_options( $following_lines ) {
        $options = array();
        $max_options = 15;

        foreach ( $following_lines as $line ) {
            if ( count( $options ) >= $max_options ) {
                break;
            }

            $trimmed = trim( $line );

            // Skip very long lines (not options)
            if ( strlen( $trimmed ) > 120 ) {
                break;
            }

            // Skip empty lines
            if ( $trimmed === '' ) {
                break;
            }

            // ── Standalone checkbox group detection ──
            // If a line starts with ☐ (or \x01) and contains 2+ checkbox items,
            // it's a standalone question group (e.g. "☐ English ☐ Afrikaans"),
            // NOT options for the preceding question. Return empty to let it
            // be detected as its own question in the next iteration.
            $checkbox_count = substr_count( $trimmed, '☐' ) + substr_count( $trimmed, '☑' ) + substr_count( $trimmed, "\x01" );
            if ( $checkbox_count >= 2 && ( preg_match( '/^[☐\x01]/u', $trimmed ) || preg_match( '/^\s*[☐\x01]/u', $trimmed ) ) ) {
                return array(); // Standalone group — not options for this question
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

            // Single checkbox: "[ ] Option" or "☐ Option" (single checkbox per line = one option)
            if ( preg_match( '/^(?:\[[ x]\]|\[?\s*[☐☑☒✓✗]\s*\]?)\s*(.{1,100})$/u', $trimmed, $m ) ) {
                $opt_text = trim( $m[1] );
                if ( strlen( $opt_text ) >= 1 && strlen( $opt_text ) <= 100 ) {
                    $options[] = $opt_text;
                }
                continue;
            }

            // PDF checkbox markers: \x01 (SOH) used as checkbox bullet
            // e.g., "\x01 English \x01 Afrikaans" — already handled by standalone check above
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
                 && ! preg_match( '/^\s*\d+[\.\)]\s+/', $trimmed )
                 && ! preg_match( '/^\s*\d+\.\d+/', $trimmed )
                 && ! preg_match( '/^[A-Z][a-z]+\s+(?:name|address|number|phone|email|date|reference|city|country|province|language|description|details|comments|notes)\b/i', $trimmed ) ) {
                // Only accept if it follows a pattern (we already detected 1+ option)
                if ( count( $options ) > 0 ) {
                    $options[] = $trimmed;
                }
            }

            // Break on any line that's clearly a new question
            if ( preg_match( '/^\s*\d+(\.\d+)?[\.\)]\s+/', $trimmed ) ) {
                break;
            }
        }

        return $options;
    }

    /**
     * Return the number of lines that were consumed as option lines.
     * Must stay in sync with detect_options() patterns.
     */
    private function detect_option_lines( $following_lines ) {
        $count = 0;
        foreach ( $following_lines as $line ) {
            $trimmed = trim( $line );

            // Skip empty or very long lines (not options)
            if ( $trimmed === '' || strlen( $trimmed ) > 120 ) {
                break;
            }

            // Bullet point or lettered options: a), b), c), A., B., C.
            if ( preg_match( '/^\s*(?:[a-z][\.\)]|[A-Z][\.\)]|•|○|◦|▪|▫|►|▸|–|—|·|‣)\s*(.{1,100})$/u', $trimmed ) ) {
                $count++;
                continue;
            }

            // Roman numeral sub-items: i), ii), iii) or I., II., III.
            if ( preg_match( '/^\s*(?:i{1,3}v{0,3}[\.\)]|I{1,3}V{0,3}[\.\)])\s*(.{1,100})$/u', $trimmed ) ) {
                $count++;
                continue;
            }

            // Dash-prefixed option
            if ( preg_match( '/^[-–]\s+(.{1,100})$/u', $trimmed ) ) {
                $count++;
                continue;
            }

            // Single checkbox: "[ ] Option" or "☐ Option"
            if ( preg_match( '/^(?:\[[ x]\]|\[?\s*[☐☑☒✓✗]\s*\]?)\s*(.{1,100})$/u', $trimmed ) ) {
                $count++;
                continue;
            }

            // PDF checkbox markers: \x01
            if ( preg_match( '/^\x01\s*(.{1,100})$/u', $trimmed ) ) {
                $count++;
                continue;
            }
            if ( strpos( $trimmed, "\x01" ) !== false ) {
                $count++;
                continue;
            }

            // Standalone checkbox group (2+ checkboxes on one line) — NOT options
            $checkbox_count = substr_count( $trimmed, '☐' ) + substr_count( $trimmed, '☑' ) + substr_count( $trimmed, "\x01" );
            if ( $checkbox_count >= 2 ) {
                break;
            }

            // Short continuation line that was accepted as option by detect_options() fallback
            // Only count if previous lines were already options (we track $count > 0)
            if ( $count > 0 && strlen( $trimmed ) <= 60 && ! preg_match( '/[?？]/u', $trimmed )
                 && ! preg_match( '/^\s*\d+[\.\)]\s+/', $trimmed )
                 && ! preg_match( '/^\s*\d+\.\d+/', $trimmed ) ) {
                $count++;
                continue;
            }

            // Not an option line — stop counting
            break;
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
     * Calculate the ratio of words starting with uppercase (Title Case detection).
     *
     * @param string $text Input text.
     * @return float Ratio 0-1.
     */
    private function title_case_ratio( $text ) {
        // Split into words
        $words = preg_split( '/[\s\-\/]+/', $text );
        $total_words = 0;
        $title_words = 0;

        foreach ( $words as $word ) {
            if ( strlen( $word ) < 2 ) {
                continue;
            }
            $total_words++;
            if ( ctype_upper( $word[0] ) && ! ctype_upper( substr( $word, 1 ) ) ) {
                $title_words++;
            } elseif ( ctype_upper( $word[0] ) && strlen( $word ) <= 3 ) {
                // Short words like "Of", "The", "And" are often uppercase in headings
                $title_words++;
            }
        }

        return ( $total_words > 0 ) ? ( $title_words / $total_words ) : 0;
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

            // Skip lines that are mostly non-printable/control characters (garbled binary data)
            if ( strlen( $text ) > 0 ) {
                $printable = 0;
                $tlen      = strlen( $text );
                for ( $k = 0; $k < $tlen; $k++ ) {
                    $ord_val = ord( $text[ $k ] );
                    if ( ( $ord_val >= 32 && $ord_val <= 126 ) || $ord_val === 9 || $ord_val === 10 || $ord_val === 13 ) {
                        $printable++;
                    }
                }
                // If less than 60% printable ASCII, it's binary garbage
                if ( $tlen > 5 && ( $printable / $tlen ) < 0.6 ) {
                    continue;
                }
            }

            // Skip common PDF header / footer noise
            if ( preg_match( '/^\s*Page\s+\d+\s*$/i', $text ) ) {
                $prev_text = '';
                continue;
            }
            if ( preg_match( '/\|.*Page\s+\d+/i', $text ) && preg_match( '/\|.*\d{3}[\s\-]\d{3}\s+\d{4}/', $text ) ) {
                $prev_text = '';
                continue;
            }

            // Skip duplicate consecutive lines
            if ( $text === $prev_text ) {
                continue;
            }

            // Merge line if previous was a cut-off word (doesn't end with punctuation/space)
            if ( ! empty( $cleaned ) && ! empty( $prev_text ) ) {
                $prev = $cleaned[ count( $cleaned ) - 1 ];
                $prev_text_str = is_array( $prev ) ? $prev['text'] : $prev;

                // Never merge if current line looks like an option line (bullet, letter, checkbox, dash)
                $looks_like_option = preg_match( '/^\s*(?:[a-z][\.\)]|[A-Z][\.\)]|•|○|◦|▪|▫|►|▸|–|—|[-–]\s+|·|‣|\[[ x]\]|\[?\s*[☐☑])/u', $text )
                    || ( strpos( $text, "\x01" ) !== false && strlen( $text ) <= 100 );
                // Never merge if current line starts with a numbered question pattern
                $looks_like_question = preg_match( '/^\s*\d+(\.\d+)?[\.\)]\s+/', $text );

                // Never merge if current line starts with a digit, uppercase, or control marker
                if ( ! preg_match( '/[\.\?!:;,\-\s]$/', $prev_text_str )
                     && strlen( $text ) < 60
                     && ! preg_match( '/^[A-Z\d\x01]/', $text )
                     && ! $looks_like_option
                     && ! $looks_like_question ) {
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

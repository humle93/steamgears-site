<?php
/**
 * AIMLAPI / Anthropic tool-schema sanitizer (WordPress-friendly, no dependencies).
 *
 * The name is historical (11/11 2026): the site is moving from AIMLAPI to OpenRouter, but
 * the rule below is Claude's own and applies through OpenRouter too, so the file stays —
 * under this name, because sgap-mcp-schema-fix.php loads it by file name on the live site.
 *
 * The Claude-style Messages API (and proxies such as aimlapi or OpenRouter) reject a request
 * when any tool's input_schema puts oneOf / anyOf / allOf at the TOP LEVEL:
 *
 *   tools.165.custom.input_schema: input_schema does not support oneOf, allOf,
 *   or anyOf at the top level
 *
 * Those keywords are fine nested inside a property; only the root of the schema
 * has to be a plain object. One bad tool fails the whole request before any
 * tool runs, so this file folds root-level combinators into the root object and
 * leaves everything else untouched.
 *
 * Drop it in anywhere inside WordPress (mu-plugin, theme includes, plugin) with:
 *
 *   require_once __DIR__ . '/aimlapi-tool-schema-sanitizer.php';
 *
 * then call it on the tool list right before the request is sent:
 *
 *   $tools = aimlapi_sanitize_tools( $tools );
 *
 * To find out which tool in your list is the guilty one, log the result of:
 *
 *   error_log( print_r( aimlapi_find_unsupported_tools( $tools ), true ) );
 *
 * How each combinator is folded:
 *   allOf      -> intersection: properties merged, required unioned.
 *   anyOf/oneOf-> union: properties merged, only the fields required by every
 *                 variant stay required. "Either a or b is required" can no
 *                 longer be expressed in the schema, so validate that in the
 *                 tool handler instead.
 *   {"type":"null"} / {"const":null} branches are dropped (that is just a
 *                 nullable root, not a real choice).
 *   A top-level $ref pointing inside the same document is resolved first.
 *   Anything that cannot become an object (a root union of scalars, say) is
 *   replaced by a permissive object so the request still goes through.
 *
 * Note: PHP encodes an empty array as [] rather than {}. Empty "properties"
 * arrays are left as-is; if your JSON encoder produces "properties": [], cast
 * it to (object) before encoding.
 */

if ( ! function_exists( 'aimlapi_sanitize_tools' ) ) {

	if ( ! defined( 'AIMLAPI_SCHEMA_MAX_DEPTH' ) ) {
		define( 'AIMLAPI_SCHEMA_MAX_DEPTH', 8 );
	}

	/**
	 * Keys that describe a schema's shape, not its metadata.
	 */
	function aimlapi_schema_structural_keys() {
		return array( 'type', 'properties', 'required', 'allOf', 'anyOf', 'oneOf' );
	}

	/**
	 * Is this schema using a composition keyword at its root?
	 */
	function aimlapi_has_root_combinator( $schema ) {
		if ( ! is_array( $schema ) ) {
			return false;
		}
		foreach ( array( 'allOf', 'anyOf', 'oneOf' ) as $key ) {
			if ( isset( $schema[ $key ] ) && is_array( $schema[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * List the tools whose schema trips the Anthropic validator, as
	 * array( array( 'index' => 165, 'name' => 'my_tool' ), ... ).
	 *
	 * @param array $tools Tool definitions, Anthropic or OpenAI style.
	 * @return array
	 */
	function aimlapi_find_unsupported_tools( $tools ) {
		$hits = array();
		if ( ! is_array( $tools ) ) {
			return $hits;
		}

		$index = 0;
		foreach ( $tools as $tool ) {
			$name   = '';
			$schema = null;

			if ( is_array( $tool ) ) {
				foreach ( array( 'input_schema', 'inputSchema', 'parameters' ) as $field ) {
					if ( isset( $tool[ $field ] ) ) {
						$schema = $tool[ $field ];
						break;
					}
				}
				if ( null === $schema && isset( $tool['function'] ) && is_array( $tool['function'] ) && isset( $tool['function']['parameters'] ) ) {
					$schema = $tool['function']['parameters'];
				}
				if ( isset( $tool['name'] ) && is_string( $tool['name'] ) ) {
					$name = $tool['name'];
				} elseif ( isset( $tool['function']['name'] ) && is_string( $tool['function']['name'] ) ) {
					$name = $tool['function']['name'];
				}
			}

			if ( is_string( $schema ) ) {
				$decoded = json_decode( $schema, true );
				$schema  = is_array( $decoded ) ? $decoded : null;
			}

			if ( aimlapi_has_root_combinator( $schema ) ) {
				$hits[] = array(
					'index' => $index,
					'name'  => $name,
				);
			}
			$index++;
		}

		return $hits;
	}

	/**
	 * Sanitizer for a whole tool list. Handles input_schema, inputSchema,
	 * parameters and OpenAI-style function.parameters.
	 *
	 * @param array $tools Tool definitions.
	 * @return array Tool definitions with compatible schemas.
	 */
	function aimlapi_sanitize_tools( $tools ) {
		if ( ! is_array( $tools ) ) {
			return $tools;
		}

		$out = array();
		foreach ( $tools as $key => $tool ) {
			if ( ! is_array( $tool ) ) {
				$out[ $key ] = $tool;
				continue;
			}

			foreach ( array( 'input_schema', 'inputSchema', 'parameters' ) as $field ) {
				if ( isset( $tool[ $field ] ) && ( is_array( $tool[ $field ] ) || is_string( $tool[ $field ] ) ) ) {
					$tool[ $field ] = aimlapi_sanitize_tool_schema( $tool[ $field ] );
				}
			}

			if ( isset( $tool['function'] ) && is_array( $tool['function'] ) && isset( $tool['function']['parameters'] ) ) {
				if ( is_array( $tool['function']['parameters'] ) || is_string( $tool['function']['parameters'] ) ) {
					$tool['function']['parameters'] = aimlapi_sanitize_tool_schema( $tool['function']['parameters'] );
				}
			}

			$out[ $key ] = $tool;
		}

		return $out;
	}

	/**
	 * Sanitizer for a single tool schema.
	 *
	 * @param array|string $schema JSON Schema as an array, or as a JSON string.
	 * @return array|string Same type that was passed in.
	 */
	function aimlapi_sanitize_tool_schema( $schema ) {
		$was_string = is_string( $schema );

		if ( $was_string ) {
			$decoded = json_decode( $schema, true );
			if ( ! is_array( $decoded ) ) {
				return $schema; // Not valid JSON: leave it alone rather than break the tool.
			}
			$schema = $decoded;
		}

		if ( ! is_array( $schema ) ) {
			$sanitized = aimlapi_permissive_schema( array() );
		} else {
			$resolved  = aimlapi_resolve_refs( $schema, $schema, 0 );
			$sanitized = aimlapi_fold_root( $resolved, 0 );
		}

		return $was_string ? json_encode( $sanitized ) : $sanitized;
	}

	/**
	 * Replace local ($ref: "#/...") references with the schema they point at.
	 */
	function aimlapi_resolve_refs( $node, $root, $depth = 0 ) {
		if ( $depth > AIMLAPI_SCHEMA_MAX_DEPTH || ! is_array( $node ) ) {
			return $node;
		}

		if ( isset( $node['$ref'] ) && is_string( $node['$ref'] ) && 0 === strpos( $node['$ref'], '#/' ) ) {
			$target = aimlapi_lookup_ref( $root, $node['$ref'] );
			if ( is_array( $target ) && $target !== $node ) {
				$merged = $target;
				foreach ( $node as $key => $value ) {
					if ( '$ref' === $key ) {
						continue;
					}
					$merged[ $key ] = $value;
				}
				return aimlapi_resolve_refs( $merged, $root, $depth + 1 );
			}
		}

		$out = array();
		foreach ( $node as $key => $value ) {
			$out[ $key ] = aimlapi_resolve_refs( $value, $root, $depth + 1 );
		}
		return $out;
	}

	/**
	 * Follow a JSON pointer such as "#/$defs/Args".
	 */
	function aimlapi_lookup_ref( $root, $ref ) {
		$path = substr( $ref, 2 );
		foreach ( explode( '/', $path ) as $segment ) {
			$segment = str_replace( array( '~1', '~0' ), array( '/', '~' ), $segment );
			if ( ! is_array( $root ) || ! array_key_exists( $segment, $root ) ) {
				return null;
			}
			$root = $root[ $segment ];
		}
		return $root;
	}

	/**
	 * Make the root of a schema a plain object schema.
	 */
	function aimlapi_fold_root( $schema, $depth = 0 ) {
		if ( ! is_array( $schema ) || $depth > AIMLAPI_SCHEMA_MAX_DEPTH ) {
			return aimlapi_permissive_schema( is_array( $schema ) ? $schema : array() );
		}

		$rest = aimlapi_metadata( $schema );

		$type = isset( $schema['type'] ) ? $schema['type'] : null;
		if ( is_array( $type ) ) {
			$types = array_values( array_diff( $type, array( 'null' ) ) );
			$type  = 1 === count( $types ) ? $types[0] : $types;
		}

		$base = $rest;
		if ( null !== $type ) {
			$base['type'] = $type;
		}
		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			$base['properties'] = $schema['properties'];
		}
		if ( isset( $schema['required'] ) && is_array( $schema['required'] ) ) {
			$base['required'] = $schema['required'];
		}

		$all_of = isset( $schema['allOf'] ) && is_array( $schema['allOf'] ) ? $schema['allOf'] : null;
		$any_of = null;
		foreach ( array( 'oneOf', 'anyOf' ) as $key ) {
			if ( null === $any_of && isset( $schema[ $key ] ) && is_array( $schema[ $key ] ) ) {
				$any_of = $schema[ $key ];
			}
		}

		// allOf is an intersection: merge the members into the root.
		if ( null !== $all_of ) {
			$merged = aimlapi_merge( array_merge( array( $base ), $all_of ), 'intersection' );
			if ( null !== $any_of ) {
				$merged['anyOf'] = $any_of; // Rare: both at once, handled on the next pass.
			}
			return aimlapi_fold_root( $merged, $depth + 1 );
		}

		// anyOf / oneOf are a union: keep one object, merge what the variants share.
		if ( null !== $any_of ) {
			$branches = array();
			foreach ( $any_of as $branch ) {
				if ( ! is_array( $branch ) || aimlapi_is_null_branch( $branch ) ) {
					continue;
				}
				$branches[] = $branch;
			}

			if ( empty( $branches ) ) {
				return aimlapi_permissive_schema( $base );
			}

			$merged = aimlapi_merge( array_merge( array( $base ), $branches ), 'union' );
			if ( empty( $merged['properties'] ) ) {
				return aimlapi_permissive_schema( $base );
			}
			return aimlapi_fold_root( $merged, $depth + 1 );
		}

		// No combinator left: this is a usable object schema.
		if ( 'object' === $type || isset( $schema['properties'] ) ) {
			$properties = isset( $schema['properties'] ) && is_array( $schema['properties'] ) ? $schema['properties'] : array();
			$required   = isset( $schema['required'] ) && is_array( $schema['required'] ) ? $schema['required'] : array();

			$out               = $rest;
			$out['type']       = 'object';
			$out['properties'] = $properties;

			$clean = array();
			foreach ( $required as $key ) {
				if ( array_key_exists( $key, $properties ) ) {
					$clean[] = $key;
				}
			}
			if ( ! empty( $clean ) ) {
				$out['required'] = array_values( array_unique( $clean ) );
			}

			return $out;
		}

		return aimlapi_permissive_schema( $base );
	}

	/**
	 * Merge several object schemas into one.
	 *
	 * @param array  $parts First element is the outer schema, the rest are variants.
	 * @param string $mode  "intersection" (allOf) or "union" (anyOf/oneOf).
	 * @return array
	 */
	function aimlapi_merge( $parts, $mode ) {
		$outer = isset( $parts[0] ) && is_array( $parts[0] ) ? $parts[0] : array();

		$outer_acc = array(
			'properties' => array(),
			'required'   => array(),
		);
		aimlapi_collect( $outer, 0, $outer_acc );

		$properties = $outer_acc['properties'];
		$sets       = array();

		foreach ( array_slice( $parts, 1 ) as $branch ) {
			$acc = array(
				'properties' => array(),
				'required'   => array(),
			);
			aimlapi_collect( $branch, 0, $acc );
			$properties = array_merge( $properties, $acc['properties'] );
			$sets[]     = $acc['required'];
		}

		if ( 'intersection' === $mode ) {
			$required = $outer_acc['required'];
			foreach ( $sets as $set ) {
				$required = array_merge( $required, $set );
			}
		} else {
			$common = null;
			foreach ( $sets as $set ) {
				$common = ( null === $common ) ? $set : array_values( array_intersect( $common, $set ) );
			}
			$required = array_merge( $outer_acc['required'], null === $common ? array() : $common );
		}

		$schema               = aimlapi_metadata( $outer );
		$schema['type']       = 'object';
		$schema['properties'] = $properties;

		$clean = array();
		foreach ( $required as $key ) {
			if ( array_key_exists( $key, $properties ) ) {
				$clean[] = $key;
			}
		}
		if ( ! empty( $clean ) ) {
			$schema['required'] = array_values( array_unique( $clean ) );
		}

		return $schema;
	}

	/**
	 * Walk a schema collecting properties and required keys, resolving nested
	 * combinators as it goes.
	 */
	function aimlapi_collect( $node, $depth, &$acc ) {
		if ( ! is_array( $node ) || $depth > AIMLAPI_SCHEMA_MAX_DEPTH ) {
			return;
		}

		if ( isset( $node['properties'] ) && is_array( $node['properties'] ) ) {
			foreach ( $node['properties'] as $key => $value ) {
				$acc['properties'][ $key ] = $value;
			}
		}

		if ( isset( $node['required'] ) && is_array( $node['required'] ) ) {
			foreach ( $node['required'] as $key ) {
				$acc['required'][] = $key;
			}
		}

		if ( isset( $node['allOf'] ) && is_array( $node['allOf'] ) ) {
			foreach ( $node['allOf'] as $child ) {
				aimlapi_collect( $child, $depth + 1, $acc );
			}
		}

		foreach ( array( 'oneOf', 'anyOf' ) as $key ) {
			if ( isset( $node[ $key ] ) && is_array( $node[ $key ] ) ) {
				foreach ( $node[ $key ] as $child ) {
					if ( is_array( $child ) && ! aimlapi_is_null_branch( $child ) ) {
						aimlapi_collect( $child, $depth + 1, $acc );
					}
				}
			}
		}
	}

	/**
	 * A branch that only describes "no value" (nullable root).
	 */
	function aimlapi_is_null_branch( $branch ) {
		if ( ! is_array( $branch ) ) {
			return false;
		}
		if ( isset( $branch['type'] ) ) {
			if ( 'null' === $branch['type'] ) {
				return true;
			}
			if ( is_array( $branch['type'] ) && array( 'null' ) === array_values( $branch['type'] ) ) {
				return true;
			}
		}
		if ( array_key_exists( 'const', $branch ) && null === $branch['const'] ) {
			return true;
		}
		if ( isset( $branch['enum'] ) && is_array( $branch['enum'] ) && 1 === count( $branch['enum'] ) && null === reset( $branch['enum'] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Everything in a schema except the keys that describe its shape.
	 */
	function aimlapi_metadata( $schema ) {
		$out = array();
		if ( ! is_array( $schema ) ) {
			return $out;
		}
		foreach ( $schema as $key => $value ) {
			if ( in_array( $key, aimlapi_schema_structural_keys(), true ) ) {
				continue;
			}
			$out[ $key ] = $value;
		}
		return $out;
	}

	/**
	 * Last resort: an object that accepts anything, so the request is not
	 * rejected over one tool.
	 */
	function aimlapi_permissive_schema( $base = array() ) {
		$out = array(
			'type'                 => 'object',
			'properties'           => array(),
			'additionalProperties' => true,
		);
		// Empty "properties" encodes as {} on most stacks; cast to (object) if yours emits [].
		if ( is_array( $base ) && isset( $base['description'] ) && is_string( $base['description'] ) ) {
			$out['description'] = $base['description'];
		}
		return $out;
	}
}

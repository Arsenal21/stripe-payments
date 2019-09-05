<?php

class ASPVariations {

	var $groups     = array();
	var $variations = array();
	var $prod_id    = false;

	function __construct( $prod_id ) {
		$this->prod_id = $prod_id;
		$this->_get_variations();
	}

	private function _get_variations() {
		$variations_groups = get_post_meta( $this->prod_id, 'asp_variations_groups', true );
		if ( empty( $variations_groups ) ) {
			return false;
		}
		$this->groups = $variations_groups;
		foreach ( $variations_groups as $grp_id => $group ) {
			$variations_names = get_post_meta( $this->prod_id, 'asp_variations_names', true );
			if ( ! empty( $variations_names ) ) {
				$variations_prices_orig                = get_post_meta( $this->prod_id, 'asp_variations_prices', true );
				$variations_prices                     = apply_filters( 'asp_variations_prices_filter', $variations_prices_orig, $this->prod_id );
				$variations_urls                       = get_post_meta( $this->prod_id, 'asp_variations_urls', true );
				$variations_opts                       = get_post_meta( $this->prod_id, 'asp_variations_opts', true );
				$this->variations[ $grp_id ]['names']  = $variations_names[ $grp_id ];
				$this->variations[ $grp_id ]['prices'] = $variations_prices[ $grp_id ];
				$this->variations[ $grp_id ]['urls']   = $variations_urls[ $grp_id ];
				$this->variations[ $grp_id ]['opts']   = isset( $variations_opts[ $grp_id ] ) ? $variations_opts[ $grp_id ] : 0;
			}
		}
	}

	public function get_variation( $grp_id, $var_id ) {

		if ( empty( $this->variations[ $grp_id ] ) ) {
			return false;
		}
		if ( empty( $this->variations[ $grp_id ]['names'][ $var_id ] ) ) {
			return false;
		}
		$var = array(
			'grp_id'     => $grp_id,
			'id'         => $var_id,
			'group_name' => $this->groups[ $grp_id ],
			'name'       => $this->variations[ $grp_id ]['names'][ $var_id ],
			'price'      => $this->variations[ $grp_id ]['prices'][ $var_id ],
			'url'        => $this->variations[ $grp_id ]['urls'][ $var_id ],
			'opts'       => isset( $this->variations[ $grp_id ]['opts'][ $var_id ] ) ? $this->variations[ $grp_id ]['opts'][ $var_id ] : array(),
		);
		return $var;
	}

}

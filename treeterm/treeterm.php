<?php

class cfs_treeterm extends cfs_field
{

    function __construct() {
        $this->name = 'treeterm';
        $this->label = __( 'TreeTerm', 'cfs' );
    }

    function html( $field ) {
        global $wpdb;

        $selected_posts = [];
        $available_posts = [];

        $taxonomies = [];
        if ( ! empty( $field->options['taxonomies'] ) ) {
            foreach ( $field->options['taxonomies'] as $taxonomy ) {
                $taxonomies[] = $taxonomy;
            }
        }
        else {
            $taxonomies = get_taxonomies( [ 'public' => true ] );
        }

        $args = [
            'taxonomy'   => $taxonomies,
            'hide_empty' => false,
            'fields'     => 'ids',
            'orderby'    => 'name',
            'order'      => 'ASC'
        ];

        $args = apply_filters( 'cfs_field_treeterm_query_args', $args, [ 'field' => $field ] );

        // Use older `get_terms` function signature for older versions of WP
        if ( version_compare( get_bloginfo('version'), '4.5', '<' ) ) {
            $taxonomy = $args['taxonomy'];
            unset( $args['taxonomy'] );

            $query = get_terms( $taxonomy, $args );
        } else {
            $query = get_terms( $args );
        }

        $parent_id_array = [];

        foreach ( $query as $term_id ) {
            $term = get_term( $term_id );
            $available_posts[] = (object) [
                'term_id'       => $term->term_id,
                'taxonomy'      => $term->taxonomy,
                'name'          => $term->name,
                'parent'        => $term->parent,
            ];
            if ($term->parent > 0) {
                $parent_id_array[] = $term->parent;
            }
        }

        usort($available_posts, function($a, $b) { return $a->term_id > $b->term_id; });
        $post_top_lvl_array = []; 
        foreach ($available_posts as $term) {
            $term->children = array();
            foreach ($available_posts as $term0) {
                if ($term->term_id === $term0->parent) {
                    array_push( $term->children, $term0 );
                }
            }
            if ($term->parent === 0) {
                array_push($post_top_lvl_array, $term);
            }
        }       

        if ( ! empty( $field->value ) ) {
            $results = $wpdb->get_results( "SELECT term_id, name FROM $wpdb->terms WHERE term_id IN ($field->value) ORDER BY FIELD(term_id,$field->value)" );
            foreach ( $results as $result ) {
                $selected_posts[ $result->term_id ] = $result;
            }
        }
    ?>
        <div class="filter_posts">
            <input type="text" class="cfs_filter_input" autocomplete="off" placeholder="<?php _e( 'Search terms', 'cfs' ); ?>" />
        </div>

        <div class="available_posts post_list">
        <?php foreach ( $post_top_lvl_array as $top_index => $term_top_lvl ) {
            $top_id = "lvl-1-" . $top_index;
            foreach ($term_top_lvl->children as $mid_index => $term_mid_lvl) {
                $mid_id = "lvl-2-" . $top_index . '-' . $mid_index;
                foreach ($term_mid_lvl->children as $index => $term ) {
                    $isFirst = $index === 0;
                    $class = ( isset( $selected_posts[ $term->term_id ] ) ) ? " used" : "";
                    ?>
                    <div class="flex unused" rel="<?php echo $term->term_id; ?>">
                        <div class="lvl-1 <?php echo $top_id; ?>" data-top-id="<?php echo $top_id; ?>">
                            <?php echo apply_filters( 'cfs_treeterm_lvl_1_display', $term_top_lvl->name,  $term_top_lvl->term_id, $field ); ?>
                        </div>
                        <div class="lvl-2 <?php echo $mid_id; ?>" data-mid-id="<?php echo $mid_id; ?>">
                            <?php echo apply_filters( 'cfs_treeterm_lvl_2_display', $term_mid_lvl->name,  $term_mid_lvl->term_id, $field ); ?>
                        </div>
                        <div rel="<?php echo $term->term_id; ?>" class="lvl-3<?php echo $class; ?>" title="<?php echo $term->name; ?>"><?php echo apply_filters( 'cfs_term_display', $term->name, $term->term_id, $field ); ?></div>
                    </div>
                    <?php
                }
            }
        }
        ?>
        </div>
        <div class="selected_posts post_list">
        <?php foreach ( $selected_posts as $term ) : ?>
            <div rel="<?php echo $term->term_id; ?>"><span class="remove"></span><?php echo apply_filters( 'cfs_treeterm_display', $term->name, $term->term_id, $field ); ?></div>
        <?php endforeach; ?>
        </div>
        <div class="clear"></div>
        <input type="hidden" name="<?php echo $field->input_name; ?>" class="<?php echo $field->input_class; ?>" value="<?php echo $field->value; ?>" />
    <?php
    }


    function options_html( $key, $field ) {
        $args = [ 'public' => true ];
        $choices = apply_filters( 'cfs_field_term_taxonomies', get_taxonomies( $args ) );

    ?>
        <tr class="field_option field_option_<?php echo $this->name; ?>">
            <td class="label">
                <label><?php _e('Taxonomies', 'cfs'); ?></label>
                <p class="description"><?php _e('Limit terms to the following taxonomies', 'cfs'); ?></p>
            </td>
            <td>
                <?php
                    CFS()->create_field( [
                        'type'          => 'select',
                        'input_name'    => "cfs[fields][$key][options][taxonomies]",
                        'options'       => [ 'multiple' => '1', 'choices' => $choices ],
                        'value'         => $this->get_option( $field, 'taxonomies' ),
                    ] );
                ?>
            </td>
        </tr>
        <tr class="field_option field_option_<?php echo $this->name; ?>">
            <td class="label">
                <label><?php _e( 'Limits', 'cfs' ); ?></label>
            </td>
            <td>
                <input type="text" name="cfs[fields][<?php echo $key; ?>][options][limit_min]" value="<?php echo $this->get_option( $field, 'limit_min' ); ?>" placeholder="min" style="width:60px" />
                <input type="text" name="cfs[fields][<?php echo $key; ?>][options][limit_max]" value="<?php echo $this->get_option( $field, 'limit_max' ); ?>" placeholder="max" style="width:60px" />
            </td>
        </tr>
    <?php
    }


    function input_head( $field = null ) {
    ?>
        <script>
        (function($) {
            update_term_values = function(field) {
                var term_ids = [];
                field.find('.selected_posts div').each(function(idx) {
                    term_ids[idx] = $(this).attr('rel');
                });
                field.find('input.treeterm').val(term_ids.join(','));
            }

            $(function() {
                const top_ids_array = [];
                const mid_ids_array = [];
                $('.lvl-1').each(function(i, obj) {
                    const temp_id = $(obj).data("top-id");
                    if ( ! top_ids_array.includes(temp_id) ) {
                        top_ids_array.push(temp_id);
                    }
                });
                $('.lvl-2').each(function(i, obj) {
                    const temp_id = $(obj).data("mid-id");
                    if ( ! mid_ids_array.includes(temp_id) ) {
                        mid_ids_array.push(temp_id);
                    }
                });

                $(document).on('cfs/ready', '.cfs_add_field', function() {
                    $('.cfs_treeterm:not(.ready)').init_term();
                });
                $('.cfs_treeterm').init_term();

                const redraw_on_off_cells = () => {
                    $('.field.cfs_treeterm').each(function (index, containObj) {
                        top_ids_array.forEach((top_id) => {
                            $(containObj).find('.unused .' + top_id).each(function(i, obj) {
                                if (i === 0) {
                                    $(obj).removeClass('invisible').addClass('visible');
                                } else {
                                    $(obj).removeClass('visible').addClass('invisible');
                                }
                            });
                        });
                        mid_ids_array.forEach((mid_id) => {
                            $(containObj).find('.unused .' + mid_id).each(function(i, obj) {
                                if (i === 0) {
                                    $(obj).removeClass('invisible').addClass('visible');
                                } else {
                                    $(obj).removeClass('visible').addClass('invisible');
                                }
                            })
                        })

                    })
                }

                redraw_on_off_cells();

                // add selected post
                $(document).on('click', '.cfs_treeterm .available_posts div.lvl-3', function() {
                    var parent = $(this).closest('.field');
                    var term_id = $(this).attr('rel');
                    var html = $(this).html();
                    $(this).closest('.flex').removeClass('unused').addClass('used');
                    const selected_ids_array = [];
                    let before_sibling = null;
                    parent.find('.selected_posts>div').each(function(i, obj) {
                        if ( term_id < $(obj).attr('rel') ) {
                            before_sibling = $(obj);
                            return false;
                        }
                    })
                    if ( before_sibling ) {
                        $('<div rel="'+term_id+'"><span class="remove"></span>'+html+'</div>').insertBefore(before_sibling);
                    } else {
                        parent.find('.selected_posts').append('<div rel="'+term_id+'"><span class="remove"></span>'+html+'</div>');
                    }

                    redraw_on_off_cells();
                    update_term_values(parent);
                });

                // remove selected post
                $(document).on('click', '.cfs_treeterm .selected_posts .remove', function() {
                    var div = $(this).parent();
                    var parent = div.closest('.field');
                    var term_id = div.attr('rel');
                    parent.find('.available_posts div.flex[rel='+term_id+']').removeClass('used').addClass('unused');
                    div.remove();
                    redraw_on_off_cells();
                    update_term_values(parent);
                });

                // filter posts
                $(document).on('keyup', '.cfs_treeterm .cfs_filter_input', function() {
                    var input = $(this).val();
                    var parent = $(this).closest('.field');
                    var regex = new RegExp(input, 'i');
                    parent.find('.available_posts div:not(.used)').each(function() {
                        if (-1 < $(this).html().search(regex)) {
                            $(this).removeClass('hidden');
                        }
                        else {
                            $(this).addClass('hidden');
                        }
                    });
                    redraw_on_off_cells();
                });
            });

            $.fn.init_term = function() {
                this.each(function() {
                    var $this = $(this);
                    $this.addClass('ready');

                    // sortable
                    $this.find('.selected_posts').sortable({
                        axis: 'y',
                        update: function(event, ui) {
                            var parent = $(this).closest('.field');
                            update_term_values(parent);
                        }
                    });
                });
            }
        })(jQuery);
        </script>
    <?php
    }


    function prepare_value( $value, $field = null ) {
        return $value;
    }


    function format_value_for_input( $value, $field = null ) {
        return empty( $value ) ? '' : implode( ',', $value );
    }


    function pre_save( $value, $field = null ) {
        if ( ! empty( $value ) ) {

            // Inside a loop, the value is $value[0]
            $value = (array) $value;

            // The raw input saves a comma-separated string
            if ( false !== strpos( $value[0], ',' ) ) {
                return explode( ',', $value[0] );
            }

            return $value;
        }

        return [];
    }
}
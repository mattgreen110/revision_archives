<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once PATH_THIRD.'revision_archives/config.php';

/**
 * Revision Tracker Extension
 *
 * @author      Matt Green
 * @copyright   Copyright (c) 2013
 * @link        https://github.com/mattgreen110/revision_archives
 */

class Revision_archives_ext {

    public $name           = RA_NAME;
    public $version        = RA_VERSION;
    public $description    = RA_DESC;
    public $settings_exist = RA_SETTINGS;
    public $docs_url       = RA_DOCS;

    public function __construct( $settings = '' )
    {
        $this->EE = get_instance();
        $this->settings = $settings;
    }

    // --------------------------------
    //  Settings
    // --------------------------------

    function settings()
    {
        $settings = array();
        $settings['api_key']            = array('i', '', $this->EE->config->item('ra_api_key'));
        $settings['output_folder']      = array('i', '', $this->EE->config->item('ra_output_folder'));
        $settings['disable_channels']   = array('ms', $this->get_channels($this->EE->config->item('site_id')), '');
        $settings['display_images']     = array('r', array('y' => "Yes", 'n' => "No"), 'y');
        $settings['max_img_width']      = array('i', '', '200');

        return $settings;
    }

    // --------------------------------
    //  Get channels
    // --------------------------------  
    function get_channels($site_id)
    {
        ee()->db->where('site_id', $site_id);
        $query = ee()->db->get('exp_channels');

        $channels = array();
        foreach ($query->result() as $row)
        {
            $channels[$row->channel_id] = $row->channel_title;
        }
        return $channels;
    }

    // ----------------------------------------------------------------------------
    //  Get the correct directory urls or paths from the config file for the fields
    // ----------------------------------------------------------------------------

    public function get_file_attributes($attr, $out)
    {
        $dir = $this->EE->config->item('upload_preferences');

        // passing the directory id directly (probabaly unserialized matrix array)
        if (is_numeric($out))
        {
            return $dir[$out][$attr];
        }
        // need to search within the field and find/replace
        else 
        {
            // loop through the file directories and pull out our winner
            for ($i=1; $i < count($dir); $i++) 
            { 
                $unparsed = '{filedir_' . $i . '}'; 

                $parsed = $dir[$i][$attr];
                
                if(strpos($out, $unparsed) !== false) 
                {
                    return str_replace($unparsed,$parsed,$out); 
                }
            }
        }
    }

    // -----------------------------------------------------------------------
    //  Takes time output such as 1401 and converts it to standard time (2:01)
    // -----------------------------------------------------------------------

    function readable_time($time)
    {
        $length = strlen($time) / 2;
        $new_time = substr($time, 0, $length) . ':' . substr($time, $length);
        return date("g:i a", strtotime( $time ));
    }

    // -----------------------------------
    //  Entry Submission Absolute End Hook
    // -----------------------------------

    function entry_submission_absolute_end($entry_id, $meta, $data, $view_url){

        if (!in_array($meta['channel_id'], $this->settings['disable_channels'])) 
        {

            // Get structure or pages url
            $out = array('structure_url' => null); 

            // Check if Structure or Pages is enabled 
            ee()->db->where('module_name', 'Structure');
            ee()->db->or_where('module_name', 'Pages'); 
            
            if ( count(ee()->db->get('modules')->result()) > 0 )
            {
                // if yes, get the structure url
                $site_pages = $this->EE->config->item('site_pages');
                $site_pages = $site_pages[$meta['site_id']];

                if (@isset($site_pages['uris'][$data['entry_id']])) 
                {
                    $out['structure_url'] = $site_pages['uris'][@$data['entry_id']];
                }
            }

            // --------------------------------
            //  Basic variables
            // --------------------------------
            
            // Entry title
            $title = $meta['title'];     

            // Todays date
            $date = date("F j, Y, g:i a");

            // Date entry was created
            $original_date = date("F j, Y, g:i a", $meta['entry_date']);

            // Who just now posted this edit
            $author = ee()->session->userdata('screen_name') . ', ' . ee()->session->userdata('email');

            // Entry id
            $entry_id = $data['entry_id'];

            // Channel it is from
            $channel_id = $meta['channel_id'];

            // The fiename the PDF will be output as 
            $final_filename = date('m-d-Y_hia') . '-' . $meta['url_title'].'.pdf';

            // Structure or pages url
            $entry_url = substr($this->EE->config->item('site_url'), 0, -1) . $out['structure_url'];

            // Site label defined in the config gile
            $site = $this->EE->config->item('site_label');

            // Current Base URL
            $url = $this->EE->config->item('base_url');

            // Url without the slash at the end
            $url_no_slash = substr($url, 0, -1);

            // Themes folder (uses the CSS for the output of the PDF HTML)
            $theme_folder_url = URL_THIRD_THEMES . 'revision_archives';

            // The file that comes from PDF crowd for their API
            require 'pdfcrowd.php';

            try
            {      
                // create an API client instance
                $client = new Pdfcrowd("jgreen", $this->settings['api_key']);

                // Begin building the html output
                $output =   "<!DOCTYPE html>
                            <html>
                                <head>
                                    <meta charset='utf-8'>
                                    <title>$title</title>
                                    <link href='http://fonts.googleapis.com/css?family=Merriweather+Sans:400,800,700,300' rel='stylesheet' type='text/css'>
                                    <link rel='stylesheet' href='$theme_folder_url/css/normalize.css' />
                                    <link rel='stylesheet' href='$theme_folder_url/css/style.css' />
                                </head>
                                <body>
                                <h1>$title</h1>
                                <hr />
                                <p>
                                    <strong>Time of Edit:</strong> $date<br />
                                    <strong>Original Post Date:</strong> $original_date<br />
                                    <strong>Url:</strong> $entry_url<br />
                                    <strong>Author of Edit:</strong> $author<br />
                                    <strong>Site:</strong> $site<br />
                                    <strong>Entry ID:</strong> $entry_id
                                </p>";

                // So that nothing is manually outputted per channel
                ee()->db->select('field_id, field_label, field_type');
                ee()->db->from('exp_channel_fields');
                $query = ee()->db->get();

                if ($query->num_rows() > 0)
                {
                    // Give me all of the custom field data
                    foreach($query->result() as $row)
                    {
                        // I need these in a more simple variables
                        $field = 'field_id_' . $row->field_id;
                        $field_type = $row->field_type; 

                        // Make sure it's there
                        if (isset($data[$field]) && !empty($data[$field]))
                        {
                            // Field labels
                            $field_label = '<h2>' . $row->field_label . '</h2>'; 

                            // Field id
                            $field_id = $row->field_id;

                            // Add to the output variables based on fieldtype
                            switch ($field_type) 
                            {
                                case 'matrix':

                                    // Get more information from matrix so I can pull the data out from the serialized data
                                    ee()->db->select('*');
                                    ee()->db->from('exp_matrix_cols');
                                    ee()->db->join('exp_matrix_data', 'exp_matrix_cols.field_id = exp_matrix_data.field_id');
                                    ee()->db->where('exp_matrix_cols.field_id', $field_id);
                                    ee()->db->where('entry_id', $entry_id);
                                    $mq = ee()->db->get();

                                    // h2 - Field Label
                                    $output .= $field_label;

                                    foreach($mq->result_array() as $mr)
                                    {   
                                        // Get the matrix data and unserialize it into an array
                                        $cookie_crisp = unserialize(base64_decode($data[$field]));

                                        // Labels for the columns
                                        $output .= '<strong>' . $mr['col_label'] . ': </strong>';

                                        // Conditional matrix data output
                                        foreach ($cookie_crisp as $key => $value) 
                                        {
                                            // grab only the rows this entry needs
                                            if ($key == 'row_id_' . $mr['row_id']) 
                                            {
                                                // if the column type is a file
                                                if (is_array($value) && $mr['col_type'] == 'file')
                                                {
                                                    $directory = $value['col_id_' . $mr['col_id']]['filedir'];
                                                    $filename = $value['col_id_' . $mr['col_id']]['filename'];  
                                                    $filename_lower = strtolower($filename);   
                                                    
                                                    // for images output the actual image            
                                                    if (substr($filename_lower, -4) == '.jpg' || substr($filename_lower, -4) == '.png') 
                                                    {
                                                        $output .= '<br /><img src="' . $this->get_file_attributes('url', $directory) . $filename . '" style="max-width:' . $this->settings['max_img_width'] . '" /><br />';
                                                    }
                                                    // for pdf's show a link
                                                    else
                                                    {
                                                        $output .= '<br /><a href="' . $this->get_file_attributes('url', $directory) . $filename . '" />' . $this->get_file_attributes('url', $directory) . $filename . '</a><br />';
                                                    }
                                                }
                                                // otherwise just output as is, it is probably either text field or wygwam
                                                else
                                                {
                                                    $output .= $value['col_id_' . $mr['col_id']] . '<br />';
                                                }
                                            }
                                        }
                                    }

                                    break;

                                case 'playa':

                                    ee()->db->select('*');
                                    ee()->db->from('exp_playa_relationships');
                                    ee()->db->join('exp_channel_titles', 'exp_channel_titles.entry_id = exp_playa_relationships.parent_entry_id');
                                    ee()->db->where('exp_playa_relationships.parent_entry_id ', $entry_id);
                                    $pq = ee()->db->get();

                                    $output .= $field_label;
                                    $output .= '<ul>';

                                    foreach($pq->result() as $pr)
                                    {   
                                        $output .= '<li>' . $pr->title . '</li>';
                                    }

                                    $output .= '</ul>';

                                    break;

                                case 'calendar':

                                    ee()->db->where('entry_id', $entry_id);
                                    $cq = ee()->db->get('exp_calendar_events');

                                    $output .= $field_label;

                                    foreach($cq->result() as $cr)
                                    {   
                                        $output .= '<p>';
                                            $output .= '<strong>All day event?</strong> ' . $cr->all_day = 'n' ? 'No' : 'Yes';
                                            $output .= '<br />';                                        
                                            $output .= '<strong>Start Date/Time:</strong> ' . $cr->start_month . '/' . $cr->start_day . '/' . $cr->start_year .', ' . $this->readable_time($cr->end_time) . '<br />'; 
                                            $output .= '<strong>End Date/Time:</strong> ' . $cr->end_month . '/' . $cr->end_day . '/' . $cr->end_year .', ' . $this->readable_time($cr->end_time) . '<br />';
                                            $output .= '<strong>Repeating?</strong> ' . $cr->recurs = 'n' ? 'No' : 'Yes';
                                        $output .= '</p>';
                                    }

                                    break;  

                                case 'tag':

                                    // I wanted to put this in a unordered list so I put it in an array.
                                    $output .= $field_label;
                                    
                                    $tags = explode("\n", $data[$field]);
                                    
                                    $output .= '<ul>';

                                    foreach ($tags as $key => $value) 
                                    {
                                        $output .= '<li>' . $value . '</li>';
                                    }

                                    $output .= '</ul>';

                                    break;

                                case 'file':

                                    $output .= $field_label;

                                    $file = $data[$field];

                                    $file_lower = strtolower($file);

                                    // for images output the actual image, else show link
                                    if (substr($file_lower, -4) == '.jpg' || substr($file_lower, -4) == '.png') 
                                    {
                                        $output .= '<img src="' . $this->get_file_attributes('url', $file) . '" style="max-width:200px;" /><br />';
                                    }
                                    else 
                                    {
                                        $output .= '<a href="' . $this->get_file_attributes('url', $file) . '" />' . $this->get_file_attributes('url', $file) . '</a><br />';
                                    }   

                                    break;                                 
                                
                                default:

                                    // label
                                    $output .= $field_label;

                                    // field data jerks!
                                    $output .= $data[$field];

                                    break;
                            }
                        }
                    }
                }

                $output .= '</body></html>';

                // create the PDF already

                if ($this->settings['display_images'] == 'n')
                {
                    $client->enableImages(FALSE);
                }
                $out_file = fopen($this->settings['output_folder'] . '/' . $final_filename, "wb");
                $client->convertHTML($output, $out_file);
                fclose($out_file);
            }
            catch(PdfcrowdException $why)
            {
                echo "Pdfcrowd Error: " . $why;
            }   
        }
    }

    // --------------------------------
    //  Activate Extension
    // --------------------------------

    function activate_extension()
    {

        $data = array(
            'class'     => 'Revision_archives_ext',
            'method'    => 'entry_submission_absolute_end',
            'hook'      => 'entry_submission_absolute_end',
            'settings'  => serialize($this->settings),
            'priority'  => 10,
            'version'   => $this->version,
            'enabled'   => 'y'
        );

        ee()->db->insert('extensions', $data);
    }

    // --------------------------------
    //  Update Extension
    // --------------------------------

    function update_extension($current = '')
    {
        if ($current == '' OR $current == $this->version)
        {
            return FALSE;
        }

        if ($current < '1.0')
        {
            // Update to version 1.0
        }

        ee()->db->where('class', 'Revision_archives_ext');
        ee()->db->update('extensions', array('version' => $this->version));
    }

    // --------------------------------
    //  Delete Extension
    // --------------------------------

    function disable_extension()
    {
        ee()->db->where('class', 'Revision_archives_ext');
        ee()->db->delete('extensions');
    }

}
// END CLASS
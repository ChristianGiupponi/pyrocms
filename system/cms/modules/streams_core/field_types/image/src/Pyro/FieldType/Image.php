<?php namespace Pyro\FieldType;

use Pyro\Module\Files\Model\Folder;
use Pyro\Module\Files\Model\File as FileModel;
use Pyro\Module\Streams_core\AbstractFieldType;

/**
 * PyroStreams Image Field Type
 *
 * @package		PyroCMS\Core\Modules\Streams Core\Field Types
 * @author		Parse19
 * @copyright	Copyright (c) 2011 - 2012, Parse19
 * @license		http://parse19.com/pyrostreams/docs/license
 * @link		http://parse19.com/pyrostreams
 */
class Image extends AbstractFieldType
{
	public $field_type_slug = 'image';

	// Files are saved as 15 character strings.
	public $db_col_type = 'string';

	public $col_constraint = 15;

	public $custom_parameters = array(
		'folder',
		'resize_width',
		'resize_height',
		'keep_ratio',
		'allowed_types',
		'on_entry_destruct',
		);

	public $version = '1.3.0';

	public $author = array(
		'name' => 'Ryan Thompson - PyroCMS',
		'url' => 'http://pyrocms.com/'
		);

	public $input_is_file = true;

	// --------------------------------------------------------------------------

	public function __construct()
	{
		ci()->load->library('image_lib');
		ci()->load->library('files/files');
	}

	public function event()
	{
		$this->js('imagefield.js');
		$this->css('imagefield.css');		
	}

	// --------------------------------------------------------------------------

	/**
	 * Output form input
	 *
	 * @param	array
	 * @param	array
	 * @return	string
	 */
	public function formInput()
	{
		ci()->load->config('files/files');

		$out = '';
		
		// if there is content and it is not dummy or cleared
		if ($this->value and $this->value != 'dummy') {

			$file = FileModel::find($this->value);

			$out .= '<span class="image_remove">X</span><a class="image_link" href="'.$file->path.'" target="_break"><img src="'.$file->path.'" /></a><br />';
			$out .= form_hidden($this->form_slug, $this->value);
		
		} else {

			$file = null;

			$out .= form_hidden($this->form_slug, 'dummy');

		}

		$options['name'] 	= $this->form_slug.'_file';

		$out .= '
				<div class="fileinput fileinput-new" data-provides="fileinput">
					<div class="input-group">
						<div class="form-control uneditable-input span3" data-trigger="fileinput">
							<i class="glyphicon glyphicon-file fileinput-exists"></i>
							<span class="fileinput-filename">'.($file ? $file->name : null).'</span>
						</div>
						<span class="input-group-addon btn btn-default btn-file">
							<span class="fileinput-new">'.lang('streams:image.select_file').'</span>
							<span class="fileinput-exists">'.lang('streams:image.change').'</span>
							'.form_upload($options).'
						</span>
						<a href="#" class="input-group-addon btn btn-danger fileinput-exists" data-dismiss="fileinput"><i class="fa fa-times-circle"></i></a>
					</div>
				</div>';

		
		return $out;
	}

	// --------------------------------------------------------------------------

	/**
	 * Process before saving to database
	 *
	 * @access	public
	 * @param	array
	 * @param	obj
	 * @return	string
	 */
	public function preSave()
	{
		// If we do not have a file that is being submitted. If we do not,
		// it could be the case that we already have one, in which case just
		// return the numeric file record value.
		if ( ! isset($_FILES[$this->form_slug.'_file']['name']) or ! $_FILES[$this->form_slug.'_file']['name'])
		{
			// return what we got
			return $this->value;
		}

		ci()->load->library('files/files');

		// Resize options
		$return = \Files::upload(
			$this->getParameter('folder'),
			null,
			$this->form_slug.'_file',
			$this->getParameter('resize_width', null),
			$this->getParameter('resize_height', null),
			$this->getParameter('keep_ratio', false),
			$this->getParameter('allowed_types', '*')
			);

		if (! $return['status'])
		{
			// Shit..
			ci()->session->set_flashdata('warning', $return['message']);
			
			return null;
		}
		else
		{
			// Return the ID of the file DB entry
			return $return['data']['id'];
		}
	}

	// --------------------------------------------------------------------------

	/**
	 * Pre Output
	 *
	 * @access	public
	 * @param	array
	 * @return	string
	 */
	public function stringOutput()
	{
		if ( ! $this->value or $this->value == 'dummy' ) return null;

		// Get image data
		$image = ci()->db->select('filename, alt_attribute, description, name')->where('id', $this->value)->get('files')->row();

		if ( ! $this->value) return null;

		// This defaults to 100px wide
		return '<img src="'.site_url('files/thumb/'.$this->value).'" alt="'.$this->obviousAlt($image).'" />';
	}

	// --------------------------------------------------------------------------

	/**
	 * Process before outputting for the plugin
	 *
	 * This creates an array of data to be merged with the
	 * tag array so relationship data can be called with
	 * a {field.column} syntax
	 *
	 * @access	public
	 * @param	string
	 * @param	string
	 * @param	array
	 * @return	array
	 */
	public function pluginOutput()
	{
		if ( ! $this->value or $this->value == 'dummy' ) return null;

		ci()->load->library('files/files');

		$file = ci()->files->getFile($this->value);

		if ($file['status'])
		{
			$image = $file['data'];

			// If we don't have a path variable, we must have an
			// older style image, so let's create a local file path.
			if ( ! $image->path)
			{
				$image_data['image'] = base_url(ci()->config->item('files:path').$image->filename);
			}
			else
			{
				$image_data['image'] = str_replace('{{ url:site }}', base_url(), $image->path);
			}

			// For <img> tags only
			$alt = $this->obviousAlt($image);

			$image_data['filename']			= $image->filename;
			$image_data['name']				= $image->name;
			$image_data['alt']				= $image->alt_attribute;
			$image_data['description']		= $image->description;
			$image_data['img']				= img(array('alt' => $alt, 'src' => $image_data['image']));
			$image_data['ext']				= $image->extension;
			$image_data['mimetype']			= $image->mimetype;
			$image_data['width']			= $image->width;
			$image_data['height']			= $image->height;
			$image_data['id']				= $image->id;
			$image_data['filesize']			= $image->filesize;
			$image_data['download_count']	= $image->download_count;
			$image_data['date_added']		= $image->date_added;
			$image_data['folder_id']		= $image->folder_id;
			$image_data['folder_name']		= $image->folder_name;
			$image_data['folder_slug']		= $image->folder_slug;
			$image_data['thumb']			= site_url('files/thumb/'.$input);
			$image_data['thumb_img']		= img(array('alt' => $alt, 'src'=> site_url('files/thumb/'.$input)));

			return $image_data;
		}
	}

	// --------------------------------------------------------------------------

	/**
	 * Ran when the entry is deleted
	 * @return void
	 */
	public function entryDestruct()
	{
		if ($this->getParameter('on_entry_destruct', 'keep') == 'delete') {
			
			// Delete that file
			\Files::deleteFile($this->value);
		}
	}

	// --------------------------------------------------------------------------

	/**
	 * Choose a folder to upload to.
	 *
	 * @access	public
	 * @param	[string - value]
	 * @return	string
	 */
	public function paramFolder($value = null)
	{
		// Load the library
		ci()->load->library('files/files');

		// Get the folders
		$tree = ci()->files->folderTree();

		$tree = (array)$tree;

		if ( ! $tree)
		{
			return '<em>'.lang('streams:image.need_folder').'</em>';
		}

		$choices = array();

		foreach ($tree as $tree_item)
		{
			// We are doing this to be backwards compat
			// with PyroStreams 1.1 and below where
			// This is an array, not an object
			$tree_item = (object)$tree_item;

			$choices[$tree_item->id] = $tree_item->name;
		}

		return form_dropdown('folder', $choices, $value);
	}

	// --------------------------------------------------------------------------

	/**
	 * Param Resize Width
	 *
	 * @access	public
	 * @param	[string - value]
	 * @return	string
	 */
	public function paramResizeWidth($value = null)
	{
		return form_input('resize_width', $value);
	}

	// --------------------------------------------------------------------------

	/**
	 * Param Resize Height
	 *
	 * @access	public
	 * @param	[string - value]
	 * @return	string
	 */
	public function paramResizeHeight($value = null)
	{
		return form_input('resize_height', $value);
	}

	// --------------------------------------------------------------------------

	/**
	 * Param Allowed Types
	 *
	 * @access	public
	 * @param	[string - value]
	 * @return	string
	 */
	public function paramKeepRatio($value = null)
	{
		$choices = array('yes' => lang('global:yes'), 'no' => lang('global:no'));

		return array(
				'input' 		=> form_dropdown('keep_ratio', $choices, $value),
				'instructions'	=> lang('streams:image.keep_ratio_instr'));
	}

	// --------------------------------------------------------------------------

	/**
	 * Param Allowed Types
	 *
	 * @access	public
	 * @param	[string - value]
	 * @return	string
	 */
	public function paramAllowedTypes($value = null)
	{
		return array(
				'input'			=> form_input('allowed_types', $value),
				'instructions'	=> lang('streams:image.allowed_types_instr'));
	}

	// --------------------------------------------------------------------------

	/**
	 * Obvious alt attribute for <img> tags only
	 *
	 * @access	private
	 * @param	obj
	 * @return	string
	 */
	private function obviousAlt($image)
	{
		if ($image->alt_attribute) {
			return $image->alt_attribute;
		}
		if ($image->description) {
			return $image->description;
		}
		return $image->name;
	}
}

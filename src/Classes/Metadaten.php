<?php

namespace Schachbulle\ContaoMetadatenBundle\Classes;

/*
 * Ersetzt den Tag {{adresse::ID}} bzw. {{adresse::ID::Funktion}}
 * durch die entsprechende Adresse aus tl_adressen
 */

class Metadaten extends \Backend
{

	public function setDefault(\DataContainer $dc)
	{
		if(\Input::get('key') != 'setDefault')
		{
			// Beenden, wenn der Parameter nicht übereinstimmt
			return '';
		}

		// Objekt BackendUser importieren
		$this->import('BackendUser','User');

		// Formular wurde abgeschickt, CSS-Datei importieren
		if(\Input::post('FORM_SUBMIT') == 'tl_metadaten_default')
		{

			// Cookie setzen und zurückkehren (key=setDefault aus URL entfernen)
			\System::setCookie('BE_PAGE_OFFSET', 0, 0);
			$this->redirect(str_replace('&key=setDefault', '', \Environment::get('request')));
		}

		// Return form
		return '
<div id="tl_buttons">
<a href="'.ampersand(str_replace('&key=setDefault', '', \Environment::get('request'))).'" class="header_back" title="'.specialchars($GLOBALS['TL_LANG']['MSC']['backBTTitle']).'" accesskey="b">'.$GLOBALS['TL_LANG']['MSC']['backBT'].'</a>
</div>

<h2 class="sub_headline">'.$GLOBALS['TL_LANG']['tl_schiedsrichterverteiler']['setDefaultHeadline'][1].'</h2>
'.\Message::generate().'
<div class="tl_listing_container" id="tl_listing">
<form action="'.ampersand(\Environment::get('request'), true).'" id="tl_schiedsrichterverteiler_default" class="tl_form" method="post" enctype="multipart/form-data">
<div class="tl_formbody_edit">
<input type="hidden" name="FORM_SUBMIT" value="tl_schiedsrichterverteiler_default">
<input type="hidden" name="REQUEST_TOKEN" value="'.REQUEST_TOKEN.'">

<div class="tl_tbox">
  <h3><label for="verteiler">'.$GLOBALS['TL_LANG']['tl_schiedsrichterverteiler']['setDefaultSelectheadline'][0].'</label></h3>
  <select name="verteiler" id="verteiler" class="tl_select" onfocus="Backend.getScrollOffset()">
    '.$optionen.'
  </select>
  <p class="tl_help tl_tip" title="">'.$GLOBALS['TL_LANG']['tl_schiedsrichterverteiler']['setDefaultSelectheadline'][1].'</p>
</div>

</div>

<div class="tl_formbody_submit">

<div class="tl_submit_container">
  <input type="submit" name="save" id="save" class="tl_submit" accesskey="s" value="'.$GLOBALS['TL_LANG']['tl_schiedsrichterverteiler']['setDefaultSubmit'][0].'">
  <p class="tl_help tl_tip" title="">'.$GLOBALS['TL_LANG']['tl_schiedsrichterverteiler']['setDefaultSubmit'][1].'</p>
</div>

</div>
</form>
</div>'; 
	}
}

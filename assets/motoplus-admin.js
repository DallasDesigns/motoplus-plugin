jQuery(function($){
  let frame;
  $('#motoplus-add-gallery').on('click', function(e){
    e.preventDefault();
    frame = wp.media({ title: 'Choose Vehicle Images', button: { text: 'Use Images' }, multiple: true });
    frame.on('select', function(){
      const ids = [];
      let html = '';
      frame.state().get('selection').each(function(att){
        const a = att.toJSON(); ids.push(a.id);
        html += '<img src="'+(a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url)+'" alt="">';
      });
      $('#motoplus_gallery').val(ids.join(','));
      $('#motoplus-gallery-preview').html(html || '<em>No gallery images selected.</em>');
    });
    frame.open();
  });
  $('#motoplus-clear-gallery').on('click', function(){ $('#motoplus_gallery').val(''); $('#motoplus-gallery-preview').html('<em>No gallery images selected.</em>'); });

  $('#motoplus_lookup_vehicle').on('click', function(){
    const reg = $('#motoplus_registration_lookup').val() || $('#motoplus_registration').val();
    $('#motoplus_lookup_result').text('Checking...');
    $.post(motoplusAdmin.ajaxUrl, { action:'motoplus_lookup_vehicle', nonce: motoplusAdmin.nonce, registration: reg }, function(resp){
      if(resp.success && resp.data.fields){
        Object.keys(resp.data.fields).forEach(function(k){ $('#motoplus_'+k).val(resp.data.fields[k]); });
        $('#motoplus_lookup_result').text('Vehicle details added.');
      } else {
        $('#motoplus_lookup_result').text(resp.data && resp.data.message ? resp.data.message : 'Lookup unavailable.');
      }
    });
  });

  $('#motoplus_generate_description').on('click', function(){
    const vehicle = {};
    $('.motoplus-admin-panel [id^="motoplus_"]').each(function(){
      const id = $(this).attr('id').replace('motoplus_','');
      vehicle[id] = $(this).val();
    });
    $('#motoplus_ai_result').text('Generating...');
    $.post(motoplusAdmin.ajaxUrl, { action:'motoplus_generate_description', nonce: motoplusAdmin.nonce, vehicle: vehicle }, function(resp){
      if(resp.success){
        if(typeof tinymce !== 'undefined' && tinymce.get('content')) tinymce.get('content').setContent(resp.data.description.replace(/\n/g,'<br>'));
        else $('#content').val(resp.data.description);
        $('#motoplus_ai_result').text('Draft description added.');
      } else $('#motoplus_ai_result').text('Could not generate description.');
    });
  });
});

jQuery(function($){
  $('#motoplus_import_html_btn').on('click', function(e){
    e.preventDefault();
    const html = $('#motoplus_import_html').val();
    $('#motoplus_import_html_result').text('Extracting vehicle details and importing images...');
    $('#motoplus_import_preview').html('');
    $.post(motoplusAdmin.ajaxUrl, { action:'motoplus_import_html', nonce: motoplusAdmin.nonce, html: html, source_url: ($('#motoplus_import_source_url').val() || 'https://www.usedcarsni.com/') }, function(resp){
      if(resp.success){
        $('#motoplus_import_html_result').text(resp.data.message);
        $('#motoplus_import_preview').html('<div class="notice notice-success inline"><p><strong>'+resp.data.title+'</strong> imported with '+resp.data.image_count+' images.</p><p><a class="button button-primary" href="'+resp.data.edit_url+'">Review Draft Vehicle</a></p></div>');
      } else {
        $('#motoplus_import_html_result').text(resp.data && resp.data.message ? resp.data.message : 'Import failed.');
      }
    }).fail(function(){
      $('#motoplus_import_html_result').text('Import failed. The pasted HTML may be too large for your server settings.');
    });
  });

  $('#motoplus_import_usedcarsni').on('click', function(e){
    e.preventDefault();
    const url = $('#motoplus_import_url').val();
    $('#motoplus_import_result').text('Importing, this can take a moment while images download...');
    $('#motoplus_import_preview').html('');
    $.post(motoplusAdmin.ajaxUrl, { action:'motoplus_import_usedcarsni', nonce: motoplusAdmin.nonce, url: url }, function(resp){
      if(resp.success){
        $('#motoplus_import_result').text(resp.data.message);
        $('#motoplus_import_preview').html('<div class="notice notice-success inline"><p><strong>'+resp.data.title+'</strong> imported with '+resp.data.image_count+' images.</p><p><a class="button button-primary" href="'+resp.data.edit_url+'">Review Draft Vehicle</a></p></div>');
      } else {
        $('#motoplus_import_result').text(resp.data && resp.data.message ? resp.data.message : 'Import failed.');
      }
    }).fail(function(){
      $('#motoplus_import_result').text('Import failed. The server may have blocked the request or timed out.');
    });
  });
});

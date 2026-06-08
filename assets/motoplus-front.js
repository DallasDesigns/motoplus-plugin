jQuery(function($){
  $('.motoplus-lead-form').on('submit', function(e){
    e.preventDefault();
    const $form = $(this), $result = $form.find('.motoplus-lead-result');
    $result.text('Sending...');
    const data = $form.serializeArray();
    data.push({name:'action', value:'motoplus_submit_lead'});
    data.push({name:'nonce', value:motoplusFront.nonce});
    $.post(motoplusFront.ajaxUrl, data, function(resp){
      $result.text(resp.data && resp.data.message ? resp.data.message : 'Done.');
      if(resp.success) $form[0].reset();
    });
  });
});

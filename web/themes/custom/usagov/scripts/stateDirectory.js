jQuery(document).ready(function ($) {
    $('#statelist').after('<label class="visuallyhidden">Select your state:<select class="usa-select usa-sr-only usa-combo-box__select" name="state-info" id="stateselect" aria-hidden="true" tabindex="-1"></select></label>');
    $('#statelist li a').each(function(){
      $('#stateselect').append('<option value="'+$(this).attr('href')+'">'+$(this).text()+'</option>');
    });
    var b;
    if($('html').attr('lang')=="en"){
     // $('#test').after('<header><h2><label for="stateselect">Find your state or territory:</label></h2></header>');
      b=$('<button id="statego" class="usa-button sd-go-btn" type="submit">Go</button>');
    }else{
     // $('#statelist').after('<header><h2><label for="stateselect">Encuentre su estado o territorio:</label></h2></header>');
      b=$('<button id="statego" class="usa-button sd-go-btn" type="submit">Ir</button>');
    }
    $('#statelist').remove();
   
    var url=$('#stateselect').val();
    b.click(function(){
      window.location.href = url;
    });
    $('#state-go').after(b);
    $('#stateselect').on('change', function(){
      url=$(this).val();
    });
  });
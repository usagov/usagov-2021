jQuery(document).ready(function ($) {
    $('#statelist').after('<div class="custom-select"><select id="stateselect" name="stateselect"></div>');
    $('#statelist li a').each(function(){
      $('#stateselect').append('<option value="'+$(this).attr('href')+'">'+$(this).text()+'</option>');
    });
    var b;
    if($('html').attr('lang')=="en"){
      $('#statelist').after('<header><h2><label for="stateselect">Find your state or territory:</label></h2></header>');
      b=$('<button id="statego">Go</button>');
    }else{
      $('#statelist').after('<header><h2><label for="stateselect">Encuentre su estado o territorio:</label></h2></header>');
      b=$('<button id="statego">Ir</button>');
    }
    $('#statelist').remove();
  
    var url=$('#stateselect').val();
    b.click(function(){
      window.location.href = url;
    });
    $('#stateselect').parent().after(b);
    $('#stateselect').on('change', function(){
      url=$(this).val();
    });
  });
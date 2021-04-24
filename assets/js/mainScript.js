//get the select file and upload to server 
function ajaxUpload() {
    
    var inputFile = document.getElementById('CsvInputFile');
    var file = inputFile.files[0];
    $("#fileHelp").html('');
    if(file.type == "text/csv"){

            var data = new FormData();
            data.append('csv', file, file.name);
            $.ajax({
                url: "/uploadfile",
                type: "POST",
                data: data,
                processData: false,
                contentType: false,
                cache: false,

                success: function(res) {
                   //build dynamic table just for view
                    if(res.status){

                        var e = $('<tr><th>Customer ID </th>'+
                        '<th scope="col" >Number of calls within same continent</th>'+
                        '<th scope="col">Total Duration of calls within same continent</th>'+
                        '<th scope="col">Total number of all calls</th>'+
                        '<th scope="col">Total duration of all calls</th>'+
                        '</tr>');  
                        $('#listOfClientData').html('');  
                        $('#listOfClientData').append(e);  


                        for (const [key] of Object.entries(res.data)) {
                           
                            var e = $('<tr><td id = "customerId"></td>'+
                            '<td id = "count_same_continent"></td> '+
                            '<td id = "total_duration_same_continent"></td>'+
                            '<td id = "total_all_calls"></td>'+
                            '<td id = "total_duration_all_calls"></td>'+
                            
                            '</tr>');
                            
                            $('#customerId', e).html(key);  

                            $('#count_same_continent', e).html(res.data[key].count_same_continent);  

                            $('#total_duration_same_continent', e).html(res.data[key].total_duration_same_continent + "<span> sec <span>"); 

                            $('#total_all_calls', e).html(res.data[key].total_all_calls);  

                            $('#total_duration_all_calls', e).html(res.data[key].total_duration_all_calls + "<span> sec <span>");  
                             
                            
                            $('#listOfClientData').append(e); 


                            //console.log(res.data[key]);
                          }

                    }else{
                        $("#fileHelp").html('<p class="text-danger">'+res.error_msg+'</p>');
                    }
                     
                 } ,
                 error:function(err){
                    console.error(err);
                    alert("error");
                  }
            });

    }else{
        $("#fileHelp").html('<p class="text-danger">Please upload csv file only</p>');

    }
    

}


$("#uploadBtn").on("click",function(e){
    e.preventDefault();
    ajaxUpload();
});


 module.exports = {
    ajaxUpload: ajaxUpload
 };


 
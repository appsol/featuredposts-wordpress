/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

jQuery(document).ready(function($){
    /**
     * Clone one of the new_slides slide-select divs and
     * attache it to the fields div, updating field names and ids
     */
    $('#new_slide_buttons button').live('click', function(){
	var type = $(this).val()
	var widget = $(this).parents('form')
	var slide = $(widget).find('#new_slides .select-' + type).clone().hide('fast', function(){
	    var new_order = Number($(widget).find('#slides .slide-select:last-child .slide-order').val())
	    new_order = isNaN(new_order)? 1 : new_order + 1
	    var old_order = Number($(this).find('.slide-order').val())
	    $(this).find('.slide-order').val(new_order)
	    var namepattern = new RegExp('\\[featured\\]\\[' + old_order + '\\]', 'gi')
	    var namereplace = '[featured][' + new_order + ']'
	    var idpattern = new RegExp('featured\\_' + old_order + '\\_', 'gi')
	    var idreplace = 'featured_' + new_order + '_'
	    $(this).find(':input').each(function(){
		$(this).attr('name', function(){
		    return String(this.name).replace(namepattern, namereplace)
		})
		$(this).attr('id', function(){
		    return String(this.id).replace(idpattern, idreplace)
		})
	    })
	    $(this).find('label').each(function(){
		$(this).attr('for', function(){
		    return $(this).siblings(':input')[0].id
		})
	    })
	})
	$(widget).find('#slides').append(slide).find('.slide-select:hidden').show('slow')
	return false
    })
    $('#slides .remove-slide').live('click', function(){
	$(this).parents('.slide-select').hide('slow').remove()
	return false
    })
})
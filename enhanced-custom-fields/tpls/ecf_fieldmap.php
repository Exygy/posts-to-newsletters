<script src="http://maps.google.com/maps?file=api&amp;v=2&amp;key=<?=$this->api_key?>" type="text/javascript"></script>
<?php if(!empty($this->value)) : ?>
    <input type="hidden" name="<?=$this->name?>" value="<?=$this->value?>" id="<?=$this->name?>" />
<?php else: ?>
    <input type="hidden" name="<?=$this->name?>" value="" id="<?=$this->name?>" />
<?php endif ?>
<div class="cl">&nbsp;</div>
<div id="map_<?=$this->name?>" style="width: 500px; height: 300px; border: solid 2px #dfdfdf; overflow: hidden;"></div>
<script type="text/javascript" charset="utf-8">
    var map_<?=$this->name?> = new GMap2(document.getElementById("map_<?=$this->name?>"));
    map_<?=$this->name?>.addControl(new GLargeMapControl());
    map_<?=$this->name?>.addControl(new GMapTypeControl());
    <?php if(!empty($this->value)) : ?>
        map_<?=$this->name?>.setCenter(new GLatLng(<?=$this->value?>), <?=$this->zoom?>);
        var marker = new GMarker(new GLatLng(<?=$this->value?>), {'draggable': true});
        marker.enableDragging();
        GEvent.addListener(marker, 'dragend', change_coords);
        map_<?=$this->name?>.addOverlay(marker);        
    <?php else: ?>
        map_<?=$this->name?>.setCenter(new GLatLng(<?=$this->lat?>, <?=$this->long?>), <?=$this->zoom?>);
    <?php endif; ?>    
    
    map_<?=$this->name?>.enableScrollWheelZoom();
    map_<?=$this->name?>.disableDoubleClickZoom();
    function change_coords(point) {
        document.getElementById("<?=$this->name?>").value = point.lat() + "," + point.lng();
    }
    function set_coords(overlay, point) {
        map_<?=$this->name?>.clearOverlays();
        if (point) {
            var marker = new GMarker(point, {'draggable': true});
            marker.enableDragging();
            GEvent.addListener(marker, 'dragend', change_coords);
            map_<?=$this->name?>.addOverlay(marker);
        }
        change_coords(point);
        return false;
    }
    GEvent.addListener(map_<?=$this->name?>, "dblclick", set_coords);
</script>

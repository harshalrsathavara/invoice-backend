{{--
    The live / deleted / all switch, shared by the customers, items and bills
    lists so the three read the same way. $filters comes from the controller.
--}}
<div class="field">
    <label for="records">Show</label>
    <select id="records" name="records">
        <option value="live" @selected(($filters['records'] ?? 'live') === 'live')>On the list</option>
        <option value="deleted" @selected(($filters['records'] ?? null) === 'deleted')>Deleted</option>
        <option value="all" @selected(($filters['records'] ?? null) === 'all')>All, including deleted</option>
    </select>
</div>

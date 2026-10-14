<?php
/**
 * Shared Name/Description fields — included by both the standalone Edit
 * page (form.php) and the inline "Add Department" modal on index.php, so
 * the two never drift out of sync with two copies of the same markup.
 * $department is null on create, the row array on edit.
 */
?>
<div class="mb-3">
    <label class="form-label">Name</label>
    <input type="text" name="name" class="form-control" value="<?= esc($department['name'] ?? old('name') ?? '') ?>" required maxlength="100">
</div>
<div class="mb-3">
    <label class="form-label">Description</label>
    <textarea name="description" class="form-control" rows="3"><?= esc($department['description'] ?? old('description') ?? '') ?></textarea>
</div>

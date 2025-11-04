<?php
/*
 * The MIT License
 *
 * Copyright 2025 chris.
 * ... (Rest of the original MIT license text)
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
?>

<div id="uploadProgressOverlay">
    <div id="uploadProgressContent">
        <span id="uploadStatusText"></span>
        <div id="uploadProgressBar"></div>
    </div>
</div>

<fieldset>
    <legend><?= $this->title; ?></legend>

    <div class="controls" style="margin-bottom: 15px;">
        <button class="button small-action edit" id="createDirBtn"><?php echo _('Create New Directory'); ?></button>
        <button class="button small-action delete" id="deleteSelectedBtn" disabled><?php echo _('Delete Selected Items'); ?></button>
    </div>

    <div class="file-manager-container">

        <div class="file-panel" id="leftPanel" data-current-path="<?php echo htmlspecialchars($this->manager['currentPath']); ?>">
            <div class="path-display">Path: <span class="current-path">/<?php echo htmlspecialchars($this->manager['currentPath']); ?></span></div>
            <ul class="file-list">
            </ul>
        </div>

        <div class="file-panel" id="rightPanel" data-current-path="<?php echo htmlspecialchars($this->manager['currentPath']); ?>">
            <div class="path-display">Path: <span class="current-path">/<?php echo htmlspecialchars($this->manager['currentPath']); ?></span></div>
            <ul class="file-list">
            </ul>
        </div>
    </div>
</fieldset>

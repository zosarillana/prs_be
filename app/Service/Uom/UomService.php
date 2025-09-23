<?php

namespace App\Service\Uom;

use App\Models\Uom;

class UomService
{
    public function getAll()
    {
        return Uom::all();
    }

    public function find($id)
    {
        return Uom::find($id);
    }

    public function create(array $data)
    {
        return Uom::create($data);
    }

    public function update($id, array $data)
    {
        $uom = Uom::find($id);

        if (! $uom) {
            return null;
        }

        $uom->update($data);

        return $uom;
    }

    public function delete($id)
    {
        $uom = Uom::find($id);

        if (! $uom) {
            return false;
        }

        return $uom->delete();
    }

    public function query()
    {
        return Uom::query();
    }
}

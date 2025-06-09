'use client';

import { useState } from 'react';
import { ActivityFilter as FilterType, ActivityImpact, ActivityCategory, ActivityType } from '@/types/activity';
import { Button } from '@/components/ui/button';
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface ActivityFilterProps {
  onFilterChange: (filter: FilterType) => void;
}

export function ActivityFilter({ onFilterChange }: ActivityFilterProps) {
  const [filter, setFilter] = useState<FilterType>({});

  const handleFilterChange = (updates: Partial<FilterType>) => {
    const newFilter = { ...filter, ...updates };
    setFilter(newFilter);
    onFilterChange(newFilter);
  };

  const clearFilters = () => {
    setFilter({});
    onFilterChange({});
  };

  return (
    <div className="space-y-4 p-4 border rounded-lg bg-white">
      <div className="flex items-center justify-between">
        <h3 className="font-medium">Filters</h3>
        <Button
          variant="ghost"
          size="sm"
          onClick={clearFilters}
        >
          Clear filters
        </Button>
      </div>

      <div className="space-y-4">
        <div className="space-y-2">
          <Label>Impact Level</Label>
          <Select
            value={filter.impact?.[0] || ''}
            onValueChange={(value) => 
              handleFilterChange({ impact: value ? [value as ActivityImpact] : undefined })
            }
          >
            <SelectTrigger>
              <SelectValue placeholder="Select impact level" />
            </SelectTrigger>
            <SelectContent>
              <SelectGroup>
                {Object.values(ActivityImpact).map((impact) => (
                  <SelectItem key={impact} value={impact}>
                    {impact.charAt(0).toUpperCase() + impact.slice(1)}
                  </SelectItem>
                ))}
              </SelectGroup>
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-2">
          <Label>Category</Label>
          <Select
            value={filter.category?.[0] || ''}
            onValueChange={(value) => 
              handleFilterChange({ category: value ? [value as ActivityCategory] : undefined })
            }
          >
            <SelectTrigger>
              <SelectValue placeholder="Select category" />
            </SelectTrigger>
            <SelectContent>
              <SelectGroup>
                {Object.values(ActivityCategory).map((category) => (
                  <SelectItem key={category} value={category}>
                    {category.charAt(0).toUpperCase() + category.slice(1)}
                  </SelectItem>
                ))}
              </SelectGroup>
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-2">
          <Label>Action Type</Label>
          <Select
            value={filter.action?.[0] || ''}
            onValueChange={(value) => 
              handleFilterChange({ action: value ? [value as ActivityType] : undefined })
            }
          >
            <SelectTrigger>
              <SelectValue placeholder="Select action" />
            </SelectTrigger>
            <SelectContent>
              <SelectGroup>
                {Object.values(ActivityType).map((action) => (
                  <SelectItem key={action} value={action}>
                    {action.toLowerCase().replace(/_/g, ' ')}
                  </SelectItem>
                ))}
              </SelectGroup>
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-2">
          <Label>Date Range</Label>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label className="text-xs">From</Label>
              <Input
                type="date"
                value={filter.date_from?.split('T')[0] || ''}
                onChange={(e) => 
                  handleFilterChange({ 
                    date_from: e.target.value ? new Date(e.target.value).toISOString() : undefined 
                  })
                }
              />
            </div>
            <div className="space-y-2">
              <Label className="text-xs">To</Label>
              <Input
                type="date"
                value={filter.date_to?.split('T')[0] || ''}
                onChange={(e) => 
                  handleFilterChange({ 
                    date_to: e.target.value ? new Date(e.target.value).toISOString() : undefined 
                  })
                }
              />
            </div>
          </div>
        </div>

        <div className="space-y-2">
          <Label>Search</Label>
          <Input
            placeholder="Search activities..."
            value={filter.search || ''}
            onChange={(e) => handleFilterChange({ search: e.target.value })}
          />
        </div>
      </div>
    </div>
  );
}